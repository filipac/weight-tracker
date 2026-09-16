<?php

namespace App\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Shared, immutable fetch/review/publish workflow for the browser and console. */
class PublishingWorkflow
{
    public function __construct(private BlogClient $blog, private Collector $collector, private AppleHealthExport $apple) {}

    public static function rememberedDestination(): string
    {
        $destination = Cache::get('health:last_destination', 'local');

        return in_array($destination, ['local', 'production'], true) ? $destination : 'local';
    }

    public function start(string $destination, string $owner): array
    {
        $identity = $this->blog->identity($destination);
        $now = now(config('health.timezone'));
        $id = (string) Str::uuid();
        $snapshot = ['owner' => $owner, 'destination' => $destination,
            'identity' => $identity, 'today' => $now->toDateString(), 'yesterday' => $now->copy()->subDay()->toDateString(),
            'fetched_at' => $now->toIso8601String(), 'expires_at' => $now->copy()->addMinutes(config('health.preview_minutes'))->timestamp];
        Cache::put('health:preview:'.$id, $snapshot, $snapshot['expires_at'] - now()->timestamp);
        $apple = $this->apple->capture($snapshot['fetched_at']);
        Cache::put('health:apple:'.$id, $apple, max(1, $snapshot['expires_at'] - now()->timestamp));

        return ['id' => $id, 'today' => $snapshot['today'], 'yesterday' => $snapshot['yesterday'], 'expires_at' => $snapshot['expires_at'], 'tasks' => Collector::tasks(), 'fetch_concurrency' => config('health.fetch_concurrency'), 'timezone' => config('health.timezone'),
            'task_dates' => array_fill_keys(Collector::tasks(), [$snapshot['today'], $snapshot['yesterday']]), 'apple_health' => array_diff_key($apple, ['entries' => true])];
    }

    private function snapshot(string $id, string $owner): array
    {
        $snapshot = Cache::get('health:preview:'.$id);
        abort_unless($snapshot && $snapshot['expires_at'] > now()->timestamp && hash_equals($snapshot['owner'], $owner), 410, 'This preview expired or belongs to another session or console run. Fetch again.');

        return $snapshot;
    }

    public function fetch(string $id, string $owner, string $task): array
    {
        $snapshot = $this->snapshot($id, $owner);
        abort_unless(in_array($task, Collector::tasks(), true), 404);
        abort_if(Cache::has('health:prepared:'.$id), 409, 'This preview is already prepared.');
        $key = 'health:task:'.$id.':'.$task;
        $result = Cache::lock($key.':lock', 120)->block(1, function () use ($id, $key, $task, $snapshot) {
            if ($cached = Cache::get($key)) {
                return $cached;
            }
            try {
                $result = $this->collector->fetch($task, $snapshot['today'], $snapshot['yesterday'], $snapshot['fetched_at'], str_starts_with($task, 'apple_health.') ? Cache::get('health:apple:'.$id, []) : []);
            } catch (ProviderException $e) {
                $result = ['state' => $e->state, 'message' => $e->getMessage(), 'entries' => []];
            } catch (\Throwable) {
                $result = ['state' => 'error', 'message' => 'The provider could not complete this request. Fetch again to retry.', 'entries' => []];
            }
            Cache::put($key, $result, max(1, $snapshot['expires_at'] - now()->timestamp));

            return $result;
        });

        return $this->fetchStatus($task, $result, $snapshot);
    }

    /** A stopped worker must not block successful sources or erase a cached result. */
    public function failedFetch(string $id, string $owner, string $task): array
    {
        $snapshot = $this->snapshot($id, $owner);
        abort_unless(in_array($task, Collector::tasks(), true), 404);
        abort_if(Cache::has('health:prepared:'.$id), 409, 'This preview is already prepared.');
        $key = 'health:task:'.$id.':'.$task;
        $result = Cache::get($key);
        if (! $result) {
            $result = ['state' => 'error', 'message' => 'This source fetch stopped or timed out. Fetch again to retry.', 'entries' => []];
            Cache::put($key, $result, max(1, $snapshot['expires_at'] - now()->timestamp));
        }

        return $this->fetchStatus($task, $result, $snapshot);
    }

    private function fetchStatus(string $task, array $result, array $snapshot): array
    {
        return ['task' => $task, 'days' => $result['days'] ?? [],
            'checked_dates' => $result['checked_dates'] ?? [$snapshot['today'], $snapshot['yesterday']], 'state' => $result['state'], 'message' => $result['message']];
    }

    public function prepare(string $id, string $owner): array
    {
        $snapshot = $this->snapshot($id, $owner);
        if ($prepared = Cache::get('health:prepared:'.$id)) {
            return $prepared;
        }
        $entries = [];
        $statuses = [];
        foreach (Collector::tasks() as $task) {
            $result = Cache::get('health:task:'.$id.':'.$task);
            abort_unless($result, 409, 'Wait for all data requests to finish before preparing the preview.');
            $statuses[$task] = ['state' => $result['state'], 'message' => $result['message'], 'days' => $result['days'] ?? []];
            foreach ($result['entries'] as $key => $entry) {
                if (! isset($entries[$key])) {
                    $entries[$key] = $entry;

                    continue;
                }
                foreach ($entry['providers'] as $source => $section) {
                    if (! isset($entries[$key]['providers'][$source])) {
                        $entries[$key]['providers'][$source] = $section;

                        continue;
                    }
                    foreach (['metrics', 'series', 'workouts'] as $kind) {
                        if (isset($section[$kind])) {
                            $entries[$key]['providers'][$source][$kind] = array_merge($entries[$key]['providers'][$source][$kind] ?? [], $section[$kind]);
                        }
                    }
                }
            }
        }
        uasort($entries, fn ($a, $b) => strcmp($b['date'], $a['date']) ?: strcmp($a['topic'], $b['topic']));
        $prepared = ['entries' => [], 'statuses' => $statuses, 'destination' => $snapshot['destination'], 'url' => config('health.blogs.'.$snapshot['destination'].'.url')];
        abort_unless(hash_equals($snapshot['identity'], $this->blog->identity($snapshot['destination'])), 409, 'Destination configuration changed. Fetch again.');
        $existingEntries = $this->blog->readMany($snapshot['destination'], $entries);
        foreach ($entries as $key => $entry) {
            $existing = $existingEntries[$key];
            $merged = EntryContract::merge($existing['entry'] ?? [], $entry);
            $prepared['entries'][$key] = ['entry' => $merged, 'expected_revision' => $existing['revision'] ?? null,
                'operation' => empty($existing['entry']) ? 'create' : (EntryContract::fingerprint($existing['entry']) === EntryContract::fingerprint($merged) ? 'unchanged' : 'update'),
                'retained' => $this->retained($merged, $entry), 'url' => $existing['url'] ?? null];
        }
        Cache::put('health:prepared:'.$id, $prepared, max(1, $snapshot['expires_at'] - now()->timestamp));

        return $prepared;
    }

    private function retained(array $existing, array $incoming): array
    {
        $retained = [];
        foreach ($existing['providers'] ?? [] as $source => $section) {
            foreach (['metrics', 'series'] as $kind) {
                foreach ($section[$kind] as $item) {
                    if (! in_array($item['key'], array_column($incoming['providers'][$source][$kind] ?? [], 'key'), true)) {
                        $retained[] = $source.': '.$item['label'];
                    }
                }
            }
            foreach ($section['workouts'] ?? [] as $workout) {
                if (! in_array($workout['start'], array_column($incoming['providers'][$source]['workouts'] ?? [], 'start'), true)) {
                    $retained[] = 'Apple Health: '.MetricCatalog::WORKOUT_TYPES[$workout['type']].' '.$workout['start'];
                }
            }
        }

        return array_values(array_unique($retained));
    }

    public function publish(string $id, string $owner, string $destination, string $key): array
    {
        $snapshot = $this->snapshot($id, $owner);
        abort_unless($destination === $snapshot['destination'], 409, 'Destination changed. Fetch a new preview.');
        abort_unless(hash_equals($snapshot['identity'], $this->blog->identity($destination)), 409, 'Destination configuration changed. Fetch again.');
        $prepared = Cache::get('health:prepared:'.$id);
        abort_unless(isset($prepared['entries'][$key]), 422, 'Select an entry from a prepared preview.');
        $item = $prepared['entries'][$key];

        return Cache::lock('health:publish:'.$id.':'.sha1($key), 30)->block(1, fn () => $this->blog->publish($destination, $item['entry'], $item['expected_revision']));
    }

    public function cancel(string $id, string $owner): void
    {
        $this->snapshot($id, $owner);
        Cache::forget('health:preview:'.$id);
        Cache::forget('health:prepared:'.$id);
        Cache::forget('health:apple:'.$id);
        foreach (Collector::tasks() as $task) {
            Cache::forget('health:task:'.$id.':'.$task);
        }

    }
}
