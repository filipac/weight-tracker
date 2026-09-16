<?php

namespace App\Health;

use Illuminate\Process\Factory;

/** Short-lived fetch processes share the same frozen preview and persistent cache. */
class ConsoleFetcher
{
    public function __construct(private Factory $processes) {}

    public function fetch(PublishingWorkflow $workflow, string $id, string $owner, array $tasks): \Generator
    {
        $limit = max(1, min(6, (int) config('health.fetch_concurrency', 4)));
        if ($limit === 1) {
            foreach ($tasks as $task) {
                yield $task => $workflow->fetch($id, $owner, $task);
            }

            return;
        }
        if (in_array(config('cache.stores.'.config('cache.default').'.driver'), ['array', 'null'], true)) {
            throw new ProviderException('configuration', 'Parallel console fetching requires persistent cache. Use file cache or HEALTH_FETCH_CONCURRENCY=1.');
        }

        $queue = array_values($tasks);
        $running = [];
        try {
            while ($queue || $running) {
                while ($queue && count($running) < $limit) {
                    $task = array_shift($queue);
                    // Apple Health is already normalized in the frozen snapshot;
                    // reading it locally avoids booting PHP for each cache read.
                    if (str_starts_with($task, 'apple_health.')) {
                        yield $task => $workflow->fetch($id, $owner, $task);

                        continue;
                    }
                    try {
                        $running[$task] = $this->processes->path(base_path())->timeout(120)
                            ->env(['HEALTH_PREVIEW_OWNER' => $owner])
                            ->start([PHP_BINARY, base_path('artisan'), 'health:fetch-task', $id, $task, '--no-interaction']);
                    } catch (\Throwable) {
                        yield $task => $workflow->failedFetch($id, $owner, $task);
                    }
                }
                $completed = false;
                foreach ($running as $task => $process) {
                    try {
                        $process->ensureNotTimedOut();
                        if ($process->running()) {
                            continue;
                        }
                        $result = $process->wait();
                        $status = $result->successful() ? json_decode($result->output(), true) : null;
                        if (! is_array($status) || ($status['task'] ?? null) !== $task || ! isset($status['state'], $status['message'], $status['days'])) {
                            $status = $workflow->failedFetch($id, $owner, $task);
                        }
                    } catch (\Throwable) {
                        $process->stop(0);
                        $status = $workflow->failedFetch($id, $owner, $task);
                    }
                    unset($running[$task]);
                    $completed = true;
                    yield $task => $status;
                }
                if ($running && ! $completed) {
                    usleep(25_000);
                }
            }
        } finally {
            // Never leave fetches running if preview/console processing aborts.
            foreach ($running as $process) {
                $process->stop(0);
            }
        }
    }
}
