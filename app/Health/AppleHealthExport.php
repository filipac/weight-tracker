<?php

namespace App\Health;

use Carbon\CarbonImmutable;

/** Private, local Health Auto Export reader. Raw identifiers and routes never leave this class. */
class AppleHealthExport
{
    public const TASKS = ['weight', 'body-composition', 'activity', 'heart', 'sleep', 'vitals', 'mindfulness', 'workouts', 'ecg'];

    public function latestFolder(): ?string
    {
        $root = config('health.apple_health_directory');
        if (! is_dir($root)) {
            return null;
        }
        $folders = array_values(array_filter(glob($root.'/*', GLOB_ONLYDIR) ?: [], fn ($p) => ! is_link($p)));
        usort($folders, fn ($a, $b) => (filemtime($b) <=> filemtime($a)) ?: strcmp(basename($b), basename($a)));

        return $folders[0] ?? null;
    }

    public function status(): array
    {
        $folder = $this->latestFolder();

        return ['configured' => true, 'connected' => (bool) $folder, 'folder' => $folder ? basename($folder) : null,
            'message' => $folder ? 'Newest export folder (by modification time)' : 'Add a Health Auto Export folder to storage/health'];
    }

    /** Read once at preview start; subsequent tasks and publishing use this frozen numerical snapshot. */
    public function capture(string $fetchedAt): array
    {
        $folder = $this->latestFolder();
        if (! $folder) {
            return ['state' => 'unavailable', 'message' => 'No Apple Health export folder found.', 'entries' => []];
        }
        $result = ['folder' => basename($folder), 'entries' => [], 'warnings' => []];
        try {
            $files = [];
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isLink() || ! $file->isFile() || strtolower($file->getExtension()) !== 'json') {
                    continue;
                }
                $files[] = $file->getPathname();
            }
            if (! $files) {
                throw new \RuntimeException('The newest export folder contains no JSON files.');
            }
            if (count($files) > 100) {
                throw new \RuntimeException('The newest export folder exceeds the limit of 100 JSON files.');
            }
            usort($files, fn ($a, $b) => (filemtime($a) <=> filemtime($b)) ?: strcmp($a, $b));
            $bytes = 0;
            $metrics = [];
            $workouts = [];
            $ecgs = [];
            $unknown = 0;
            foreach ($files as $file) {
                $size = filesize($file);
                $bytes += $size;
                if ($size > 64 * 1024 * 1024 || $bytes > 128 * 1024 * 1024) {
                    throw new \RuntimeException('The newest export exceeds the JSON size limit (64 MB per file, 128 MB total).');
                }
                $raw = file_get_contents($file);
                if (strlen($raw) !== $size) {
                    throw new \RuntimeException('The export is still changing. Finish copying it and fetch again.');
                }
                $document = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                $data = $document['data'] ?? null;
                if (! is_array($data) || ! array_intersect(['metrics', 'workouts', 'ecg'], array_keys($data))) {
                    throw new \RuntimeException('The newest folder contains an unsupported JSON format. Use Health Auto Export JSON.');
                }
                foreach (['metrics', 'workouts', 'ecg'] as $kind) {
                    if (isset($data[$kind]) && ! is_array($data[$kind])) {
                        throw new \RuntimeException('An export section has an invalid format.');
                    }
                }
                foreach ($data['metrics'] ?? [] as $metric) {
                    if (! is_array($metric) || ! isset($metric['name'], $metric['units']) || ! is_array($metric['data'] ?? null)) {
                        throw new \RuntimeException('A health metric has an invalid format.');
                    }
                    $definitions = array_filter(MetricCatalog::all(), fn ($d) => $d['source'] === 'apple_health' && $d['endpoint'] === $metric['name']);
                    if (! $definitions) {
                        // \Log::warning('Unknown metric encountered.', ['metric' => $metric]);
                        function_exists('ray') && ray('Unknown metric encountered: '.$metric['name']);
                        function_exists('ray') && ray($metric);
                        $unknown++;

                        continue;
                    }
                    foreach ($metric['data'] as $row) {
                        $at = $this->time($row['date'] ?? null);
                        // Latest file replaces the same observation; never sum duplicate export files or sources.
                        $metrics[$metric['name'].'|'.$at] = [$metric['name'], $metric['units'], $row, $at];
                    }
                }
                foreach ($data['workouts'] ?? [] as $row) {
                    $start = $this->time($row['start'] ?? null);
                    $end = $this->time($row['end'] ?? null);
                    $workouts[$start.'|'.$this->workoutType($row['name'] ?? '')] = $row;
                }
                foreach ($data['ecg'] ?? [] as $row) {
                    $ecgs[$this->time($row['start'] ?? null)] = $row;
                }
                // Drop the decoded document before reading another file. The
                // selected observations above retain only the data still needed.
                unset($raw, $document, $data, $metric, $row);
            }
            foreach ($metrics as [$name, $unit, $row, $at]) {
                foreach (MetricCatalog::all() as $key => $def) {
                    if ($def['source'] !== 'apple_health' || $def['endpoint'] !== $name) {
                        continue;
                    }
                    $value = $row[$def['field']] ?? null;
                    if ($def['unit'] === 'timestamp') {
                        $value = $value ? CarbonImmutable::parse($this->time($value))->timestamp : null;
                    } else {
                        $value = $this->quantity($value, $unit, $def['unit']);
                    }
                    if ($value === null) {
                        if (isset($row[$def['field']])) {
                            $unknown++;
                        }

                        continue;
                    }
                    $date = substr($at, 0, 10);
                    $section = &$this->section($result['entries'], $def['topic'], $def['topic'], $date, $fetchedAt);
                    $section['metrics'][] = $this->metric($key, $value, $at);
                }
            }
            foreach ($workouts as $row) {
                $start = $this->time($row['start']);
                $end = $this->time($row['end']);
                if (strtotime($end) <= strtotime($start)) {
                    throw new \RuntimeException('A workout has invalid start/end times.');
                }
                $workout = ['type' => $this->workoutType($row['name'] ?? ''), 'start' => $start, 'end' => $end,
                    'origin' => $this->origin($row['source'] ?? []), 'metrics' => [], 'series' => []];
                foreach (MetricCatalog::all() as $key => $def) {
                    if ($def['source'] !== 'apple_health' || $def['endpoint'] !== 'workout') {
                        continue;
                    }
                    $value = data_get($row, $def['field']);
                    if (! empty($def['series'])) {
                        $points = $this->samples($value, $def['sample_field'], $def['unit']);
                        if ($points) {
                            $workout['series'][] = ['key' => $key, 'points' => $points];
                        }
                    } else {
                        $value = $this->quantity($value, $def['unit'], $def['unit']);
                        if ($value !== null) {
                            $workout['metrics'][] = $this->metric($key, $value, $start);
                        }
                    }
                }
                foreach (['splits', 'segments', 'activities'] as $group) {
                    $rows = $row[$group] ?? [];
                    // A split collection can contain kilometre and mile versions. Use the first collection only.
                    if ($group === 'splits') {
                        $rows = $rows[0]['items'] ?? [];
                    }
                    foreach ($rows as $part) {
                        $at = $this->time($part['start'] ?? null);
                        foreach (MetricCatalog::all() as $key => $def) {
                            if ($def['source'] !== 'apple_health' || $def['endpoint'] !== $group) {
                                continue;
                            }
                            $value = $this->quantity(data_get($part, $def['field']), $def['unit'], $def['unit']);
                            if ($value !== null) {
                                $workout['metrics'][] = $this->metric($key, $value, $at);
                            }
                        }
                    }
                }
                if ($workout['metrics'] || $workout['series']) {
                    $section = &$this->section($result['entries'], 'workouts', 'activity', substr($start, 0, 10), $fetchedAt);
                    $section['workouts'][] = $workout;
                }
            }
            foreach ($ecgs as $at => $row) {
                $row['duration'] = strtotime($this->time($row['end'] ?? null)) - strtotime($at);
                $section = &$this->section($result['entries'], 'ecg', 'heart', substr($at, 0, 10), $fetchedAt);
                foreach (MetricCatalog::all() as $key => $def) {
                    if ($def['source'] !== 'apple_health' || $def['endpoint'] !== 'ecg') {
                        continue;
                    }
                    if (! empty($def['series'])) {
                        $points = $this->samples($row[$def['field']] ?? [], 'voltage', 'µV');
                        if ($points) {
                            $section['series'][] = ['key' => $key, 'points' => $points];
                        }
                    } else {
                        $value = $this->quantity($row[$def['field']] ?? null, $def['unit'], $def['unit']);
                        if ($value !== null) {
                            $section['metrics'][] = $this->metric($key, $value, $at);
                        }
                    }
                }
            }
            $counts = ['metric_types' => count(array_unique(array_column($metrics, 0))),
                'workouts' => count($workouts), 'ecgs' => count($ecgs)];
            // Validation builds a normalized copy. Release raw samples and
            // private workout details first instead of keeping both in memory.
            unset($metrics, $workouts, $ecgs, $row, $workout, $rows, $part, $points, $value, $section);
            $dates = [];
            foreach ($result['entries'] as &$byDate) {
                foreach ($byDate as $date => &$entry) {
                    $entry = EntryContract::normalize($entry);
                    $dates[] = $date;
                }
            }
            unset($entry, $byDate);
            if ($unknown) {
                $result['warnings'][] = "$unknown unsupported metric names or units were skipped.";
            }
            $result += $counts + ['state' => 'ok', 'message' => 'Read newest export', 'files' => count($files), 'from' => $dates ? min($dates) : null, 'to' => $dates ? max($dates) : null];
        } catch (\Throwable $e) {
            $result['entries'] = [];
            $result['state'] = 'error';
            $result['message'] = $e instanceof \JsonException ? 'The newest export contains incomplete or invalid JSON. Finish exporting and fetch again.' : ($e instanceof \RuntimeException ? $e->getMessage() : 'The newest export could not be read. Check its format and fetch again.');
        }

        return $result;
    }

    public function fetchDay(array $snapshot, string $task, string $date): array
    {
        if (($snapshot['state'] ?? 'unavailable') !== 'ok') {
            return ['state' => $snapshot['state'] ?? 'unavailable', 'message' => $snapshot['message'] ?? 'Fetch again to read the Apple Health export.', 'entries' => []];
        }
        $entry = $snapshot['entries'][$task][$date] ?? null;

        return ['state' => $entry ? 'ok' : 'empty', 'message' => $entry ? 'Read from newest Apple Health export' : 'No exported measurements for this day', 'entries' => $entry ? [$entry['topic'].':'.$date => $entry] : []];
    }

    private function &section(array &$entries, string $task, string $topic, string $date, string $fetchedAt): array
    {
        $entries[$task][$date] ??= ['schema_version' => 1, 'topic' => $topic, 'date' => $date, 'timezone' => 'Europe/Bucharest', 'providers' => ['apple_health' => ['fetched_at' => $fetchedAt, 'metrics' => [], 'series' => []]]];

        return $entries[$task][$date]['providers']['apple_health'];
    }

    private function metric(string $key, float $value, string $at): array
    {
        return ['key' => $key, 'value' => $value, 'at' => $at];
    }

    private function time(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            throw new \RuntimeException('An exported measurement is missing its timestamp.');
        }
        if (is_numeric($value)) {
            return CarbonImmutable::createFromTimestamp((string) $value)->setTimezone('Europe/Bucharest')->format('Y-m-d\TH:i:s.uP');
        }
        // iOS exports may use a narrow no-break space before AM/PM.
        $value = preg_replace('/[\x{00a0}\x{202f}]/u', ' ', $value);
        if (! preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/D', $value)) {
            throw new \RuntimeException('Export timestamps must include a timezone offset.');
        }
        try {
            return CarbonImmutable::parse($value)->setTimezone('Europe/Bucharest')->toIso8601String();
        } catch (\Throwable) {
            throw new \RuntimeException('An export timestamp is invalid.');
        }
    }

    private function samples(mixed $rows, string $field, string $unit): array
    {
        if (! is_array($rows)) {
            return [];
        }
        if (count($rows) > 20000) {
            throw new \RuntimeException('An exported time series exceeds 20,000 points. Export shorter periods or grouped samples.');
        }
        $points = [];
        foreach ($rows as $row) {
            $value = $this->quantity($row[$field] ?? null, $row['units'] ?? $unit, $unit);
            if ($value !== null) {
                $points[] = ['at' => $this->time($row['date'] ?? null), 'value' => $value];
            }
        }

        return $points;
    }

    private function quantity(mixed $value, string $from, string $to): ?float
    {
        if (is_array($value)) {
            $from = $value['units'] ?? $from;
            $value = $value['qty'] ?? null;
        }
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            return null;
        }
        $aliases = ['count/min' => 'bpm', 'degC' => '°C', 'km/hr' => 'km/h', 'kmph' => 'km/h', 'ml/(kg·min)' => 'ml/kg/min', 'mcV' => 'µV', 'spm' => 'steps/min'];
        $from = $aliases[$from] ?? $from;
        if ($from === $to || ($from === 'count' && $to === 'index') || ($from === 'bpm' && in_array($to, ['steps/min', 'breaths/min']))) {
            return (float) $value;
        }
        if ($from === 'degF' && $to === '°C') {
            return ($value - 32) * 5 / 9;
        }
        $factors = ['kJ:kcal' => 1 / 4.184, 'J:kcal' => 1 / 4184, 'm:km' => .001, 'mi:km' => 1.609344, 'ft:m' => .3048, 'cm:m' => .01, 'm:cm' => 100, 'in:cm' => 2.54, 'lb:kg' => .45359237, 'lbs:kg' => .45359237, 'mph:km/h' => 1.609344, 'm/s:km/h' => 3.6, 'km/h:m/s' => 1 / 3.6, 'min:hr' => 1 / 60, 's:hr' => 1 / 3600, 'hr:min' => 60, 'min:s' => 60, 's:min' => 1 / 60, 'MET:kcal/hr·kg' => 1];

        return isset($factors[$from.':'.$to]) ? $value * $factors[$from.':'.$to] : null;
    }

    private function origin(mixed $source): string
    {
        $name = is_array($source) ? ($source['name'] ?? '') : (string) $source;
        if (stripos($name, 'oura') !== false) {
            return 'oura';
        }
        if (stripos($name, 'withings') !== false) {
            return 'withings';
        }

        return 'apple_health';
    }

    private function workoutType(string $name): string
    {
        return match (strtolower($name)) {
            'indoor run' => 'indoor-run', 'outdoor run' => 'outdoor-run', 'running', 'run' => 'running',
            'indoor walk' => 'indoor-walk', 'outdoor walk' => 'outdoor-walk', 'walk', 'walking' => 'walking',
            'traditional strength training', 'functional strength training', 'strength training' => 'strength',
            'indoor cycling' => 'indoor-cycling', 'outdoor cycling', 'cycling' => 'cycling', 'swimming', 'pool swim', 'open water swim' => 'swimming',
            'hiking' => 'hiking', 'yoga' => 'yoga', 'pilates' => 'pilates', 'rowing' => 'rowing', 'elliptical' => 'elliptical', 'high intensity interval training' => 'hiit', default => 'other',
        };
    }
}
