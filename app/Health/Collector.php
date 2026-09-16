<?php

namespace App\Health;

use Carbon\CarbonImmutable;

class Collector
{
    public const OURA = ['daily_activity' => 'daily', 'daily_sleep' => 'daily', 'sleep' => 'daily', 'sleep_time' => 'daily', 'daily_readiness' => 'daily', 'daily_stress' => 'stress', 'daily_resilience' => 'stress', 'daily_spo2' => 'spo2', 'daily_cardiovascular_age' => 'heart_health', 'vO2_max' => 'heart_health', 'heartrate' => 'heartrate', 'workout' => 'workout', 'session' => 'session', 'rest_mode_period' => 'daily'];

    public function __construct(private OuraClient $oura, private WithingsClient $withings, private WithingsMeasurements $measurements) {}

    public static function tasks(): array
    {
        return array_merge(['withings.weigh_in', 'withings.measurements', 'withings.activity', 'withings.workout', 'withings.sleep', 'withings.heart'], array_map(fn ($name) => 'oura.'.$name, array_keys(self::OURA)), array_map(fn ($name) => 'apple_health.'.$name, AppleHealthExport::TASKS));
    }

    public function fetch(string $task, string $today, string $yesterday, string $fetchedAt, array $appleSnapshot = []): array
    {
        $days = [];
        $entries = [];
        foreach ([$today, $yesterday] as $date) {
            try {
                $result = str_starts_with($task, 'apple_health.')
                    ? app(AppleHealthExport::class)->fetchDay($appleSnapshot, substr($task, strlen('apple_health.')), $date)
                    : $this->fetchDay($task, $date, $fetchedAt);
            } catch (ProviderException $e) {
                $result = ['state' => $e->state, 'message' => $e->getMessage(), 'entries' => []];
            } catch (\Throwable) {
                $result = ['state' => 'error', 'message' => 'The provider could not complete this request. Fetch again to retry.', 'entries' => []];
            }
            // Both dates are independent: a populated or failed day never suppresses the other.
            $days[] = ['date' => $date, 'state' => $result['state'], 'message' => $result['message']];
            $entries = array_merge($entries, $result['entries']);
        }
        $errors = array_values(array_filter($days, fn ($day) => ! in_array($day['state'], ['ok', 'empty'], true)));
        $state = $errors ? ($entries ? 'partial' : $errors[0]['state']) : ($entries ? 'ok' : 'empty');

        return ['state' => $state, 'message' => $errors ? ($entries ? 'Some data fetched; review the results for each date.' : $errors[0]['message']) : ($entries ? 'Fetched available data for both dates' : 'No measurements for either date'),
            'days' => $days, 'checked_dates' => [$today, $yesterday], 'entries' => $entries];
    }

    private function fetchDay(string $task, string $date, string $fetchedAt): array
    {
        [$source, $endpoint] = explode('.', $task, 2);
        $start = CarbonImmutable::parse($date, config('health.timezone'))->startOfDay();
        $end = $start->addDay();
        $entries = [];
        if ($source === 'withings' && in_array($endpoint, ['weigh_in', 'measurements'])) {
            $groups = $this->measurements->forDay($date);
            if ($endpoint === 'weigh_in') {
                $minimum = $this->measurements->minimum($groups);
                $groups = $minimum ? [$minimum['group']] : [];
            }
            foreach ($groups as $group) {
                foreach ($group['measures'] ?? [] as $measure) {
                    $key = 'withings.measure.'.($measure['type'] ?? '');
                    $positionKey = $key.'.position_'.($measure['position'] ?? '');
                    if (isset(MetricCatalog::all()[$positionKey])) {
                        $key = $positionKey;
                    }
                    $definition = MetricCatalog::all()[$key] ?? null;
                    if (! $definition) {
                        continue;
                    }
                    $current = in_array($definition['topic'], ['weight', 'body-composition']);
                    if ($current !== ($endpoint === 'weigh_in')) {
                        continue;
                    }
                    $this->metric($entries, $date, $key, WithingsMeasurements::value($measure), CarbonImmutable::createFromTimestamp($group['date'])->toIso8601String(), $fetchedAt);
                }
            }
        } else {
            if ($source === 'oura') {
                $params = $endpoint === 'heartrate'
                    ? ['start_datetime' => $start->toIso8601String(), 'end_datetime' => $end->toIso8601String()]
                    : ['start_date' => $date, 'end_date' => $end->toDateString()];
                // Sleep is queried by bedtime, which can be on the preceding date.
                // Fetch that night too, then keep only Oura's assigned target day.
                if (in_array($endpoint, ['sleep', 'sleep_time'], true)) {
                    $params['start_date'] = $start->subDay()->toDateString();
                }
                // Periods can start before the target day. The API filters these by start_day.
                if ($endpoint === 'rest_mode_period') {
                    $params['start_date'] = '2000-01-01';
                }
                $rows = $this->oura->collection($endpoint, $params, self::OURA[$endpoint]);
            } else {
                if (in_array($endpoint, ['activity', 'workout', 'sleep']) && ! in_array('user.activity', cache('withings')['scopes'] ?? [], true)) {
                    throw new ProviderException('permission', 'Reconnect Withings to grant activity and sleep access.');
                }
                [$path, $params, $field] = match ($endpoint) {
                    'activity' => ['v2/measure', ['action' => 'getactivity', 'startdateymd' => $date, 'enddateymd' => $date, 'data_fields' => 'steps,distance,elevation,soft,moderate,intense,calories,totalcalories,hr_average,hr_min,hr_max'], 'activities'],
                    'workout' => ['v2/measure', ['action' => 'getworkouts', 'startdateymd' => $date, 'enddateymd' => $date, 'data_fields' => 'steps,distance,calories,hr_average,hr_min,hr_max'], 'series'],
                    'sleep' => ['v2/sleep', ['action' => 'getsummary', 'startdateymd' => $date, 'enddateymd' => $date, 'data_fields' => 'deepsleepduration,lightsleepduration,remsleepduration,wakeupduration,wakeupcount,durationtosleep,durationtowakeup,hr_average,hr_min,hr_max,rr_average,rr_min,rr_max,sleep_score,sleep_efficiency,sleep_duration,total_sleep_time,total_timeinbed,snoring,snoringepisodecount,apnea_hypopnea_index'], 'series'],
                    'heart' => ['v2/heart', ['action' => 'list', 'startdate' => $start->timestamp, 'enddate' => $end->timestamp - 1], 'series'],
                };
                if (in_array($endpoint, ['activity', 'workout', 'sleep'], true)) {
                    $fields = [];
                    foreach (MetricCatalog::all() as $definition) {
                        if ($definition['source'] === 'withings' && $definition['endpoint'] === $endpoint && $definition['field'] !== 'duration') {
                            $fields[] = $definition['field'];
                        }
                    }
                    $params['data_fields'] = implode(',', $fields);
                }
                $rows = $this->withings->collection($path, $params, $field);
            }
            foreach ($rows as $row) {
                if (! $this->belongs($source, $endpoint, $row, $date, $start, $end)) {
                    continue;
                }
                $row = $source === 'withings' ? array_merge($row, $row['data'] ?? []) : $row;
                $at = $this->recordTime($source, $row, $start);
                if (in_array($endpoint, ['session', 'workout', 'rest_mode_period'])) {
                    $from = $row['start_datetime'] ?? $row['start_time'] ?? $row['startdate'] ?? null;
                    $to = $row['end_datetime'] ?? $row['end_time'] ?? $row['enddate'] ?? null;
                    if ($from && ($to || $endpoint === 'rest_mode_period')) {
                        $a = $this->time($from);
                        $b = $to ? $this->time($to) : $end;
                        if ($endpoint === 'rest_mode_period') {
                            $a = $a->max($start);
                            $b = $b->min($end)->min($this->time($fetchedAt));
                        }
                        $row['duration'] = max(0, $b->timestamp - $a->timestamp);
                    }
                }
                foreach (MetricCatalog::all() as $key => $definition) {
                    if ($definition['source'] !== $source || $definition['endpoint'] !== $endpoint) {
                        continue;
                    }
                    $value = data_get($row, $definition['field']);
                    if (! empty($definition['series'])) {
                        $points = $this->points($endpoint, $definition['field'], $value, $row, $at);
                        // Sleep series follow Oura's assigned day; other series use calendar-day boundaries.
                        if ($endpoint !== 'sleep') {
                            $points = array_values(array_filter($points, fn ($p) => $this->time($p['at'])->betweenIncluded($start, $end->subMicrosecond())));
                        }
                        if ($points) {
                            $this->series($entries, $date, $key, $points, $fetchedAt);
                        }
                    } else {
                        $this->metric($entries, $date, $key, $value, $at, $fetchedAt);
                    }
                }
            }
        }

        return ['state' => $entries ? 'ok' : 'empty', 'message' => $entries ? 'Fetched' : 'No measurements for this day', 'entries' => $entries];
    }

    private function time(mixed $value): CarbonImmutable
    {
        return is_numeric($value) ? CarbonImmutable::createFromTimestamp((int) $value) : CarbonImmutable::parse($value);
    }

    private function belongs(string $source, string $endpoint, array $row, string $date, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        if ($endpoint === 'rest_mode_period') {
            return isset($row['start_day']) && $row['start_day'] <= $date && (empty($row['end_day']) || $row['end_day'] >= $date);
        }
        if ($source === 'oura' && isset($row['day'])) {
            return $row['day'] === $date;
        }
        if ($source === 'withings' && isset($row['date']) && is_string($row['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['date'])) {
            return $row['date'] === $date;
        }
        $value = $row['timestamp'] ?? ($endpoint === 'sleep' ? ($row['enddate'] ?? null) : ($row['startdate'] ?? $row['date'] ?? null));
        if ($value === null) {
            return false;
        }
        $time = $this->time($value);

        return $time >= $start && $time < $end;
    }

    private function recordTime(string $source, array $row, CarbonImmutable $start): string
    {
        $value = $row['timestamp'] ?? $row['start_datetime'] ?? $row['bedtime_start'] ?? $row['startdate'] ?? $row['date'] ?? null;

        return ($value ? $this->time($value) : $start)->toIso8601String();
    }

    private function points(string $endpoint, string $field, mixed $value, array $row, string $at): array
    {
        if ($endpoint === 'heartrate') {
            return is_numeric($value) ? [['at' => $at, 'value' => (float) $value]] : [];
        }
        if (is_array($value) && isset($value['items'], $value['interval'], $value['timestamp'])) {
            $values = $value['items'];
            $interval = (int) $value['interval'];
            $start = $this->time($value['timestamp']);
        } elseif (is_string($value) && in_array($field, ['sleep_phase_5_min', 'movement_30_sec'])) {
            $values = str_split($value);
            $interval = $field === 'movement_30_sec' ? 30 : 300;
            $start = $this->time($at);
        } else {
            return [];
        }
        $points = [];
        foreach ($values as $i => $v) {
            if (is_numeric($v) && is_finite((float) $v)) {
                $points[] = ['at' => $start->addSeconds($interval * $i)->toIso8601String(), 'value' => (float) $v];
            }
        }

        return $points;
    }

    private function &section(array &$entries, string $date, string $key, string $fetchedAt): array
    {
        $def = MetricCatalog::all()[$key];
        $id = $def['topic'].':'.$date;
        $entries[$id] ??= ['schema_version' => 1, 'topic' => $def['topic'], 'date' => $date, 'timezone' => config('health.timezone'), 'providers' => []];
        $entries[$id]['providers'][$def['source']] ??= ['fetched_at' => $fetchedAt, 'metrics' => [], 'series' => []];

        return $entries[$id]['providers'][$def['source']];
    }

    private function metric(array &$entries, string $date, string $key, mixed $value, string $at, string $fetchedAt): void
    {
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            return;
        }
        $section = &$this->section($entries, $date, $key, $fetchedAt);
        $def = MetricCatalog::all()[$key];
        $section['metrics'][] = ['key' => $key, 'label' => $def['label'], 'unit' => $def['unit'], 'value' => (float) $value, 'at' => $at];
    }

    private function series(array &$entries, string $date, string $key, array $points, string $fetchedAt): void
    {
        $section = &$this->section($entries, $date, $key, $fetchedAt);
        $def = MetricCatalog::all()[$key];
        $index = array_search($key, array_column($section['series'], 'key'), true);
        if ($index !== false) {
            $section['series'][$index]['points'] = array_merge($section['series'][$index]['points'], $points);
        } else {
            $section['series'][] = ['key' => $key, 'label' => $def['label'], 'unit' => $def['unit'], 'points' => $points];
        }
    }
}
