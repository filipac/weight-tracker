<?php

namespace App\Health;

use Illuminate\Process\Factory;

/** Short-lived fetch processes share the same frozen preview and persistent cache. */
class ConsoleFetcher
{
    public function __construct(private Factory $processes) {}

    public function fetch(PublishingWorkflow $workflow, string $id, string $owner, array $tasks, ?callable $progress = null): \Generator
    {
        $limit = max(1, min(6, (int) config('health.fetch_concurrency', 4)));
        if ($limit > 1 && in_array(config('cache.stores.'.config('cache.default').'.driver'), ['array', 'null'], true)) {
            throw new ProviderException('configuration', 'Parallel console fetching requires persistent cache. Use file cache or HEALTH_FETCH_CONCURRENCY=1.');
        }

        $queue = array_values($tasks);
        $running = [];
        $attempts = [];
        $cooldown = 0;
        $retryDeadline = null;
        $blocked = false;
        $maxRetries = max(0, (int) config('health.withings_retry_attempts', 3));
        // Reserve a minute for preparing/publishing rather than expiring while asleep.
        $fetchDeadline = $this->clock() + max(0, $workflow->fetchExpiresAt($id, $owner) - now()->timestamp - 60);
        $accept = function (string $task, array $status) use (&$queue, &$attempts, &$cooldown, &$retryDeadline, &$blocked, $maxRetries, $fetchDeadline, $progress): bool {
            if (! str_starts_with($task, 'withings.') || ! PublishingWorkflow::isRateLimited($status) || $maxRetries === 0) {
                return true;
            }
            $retryDeadline ??= min($fetchDeadline, $this->clock() + max(0, (int) config('health.withings_retry_budget_seconds', 600)));
            $retry = $attempts[$task] ?? 0;
            $serverDelay = max(array_merge([0, $status['retry_after'] ?? 0], array_column($status['days'] ?? [], 'retry_after')));
            $delay = max($serverDelay, max(1, (int) config('health.withings_retry_seconds', 60)) * (2 ** min($retry, 10)));
            if ($this->clock() + $delay > $retryDeadline) {
                if (! $blocked) {
                    $progress && $progress('Withings retry wait exceeds the remaining retry budget or preview lifetime. Continuing with available data.');
                }
                $blocked = true;

                return true;
            }
            $cooldown = max($cooldown, $this->clock() + $delay);
            if ($retry >= $maxRetries) {
                $progress && $progress($task.' · Withings retry limit reached; continuing with available data.');

                return true;
            }
            $attempts[$task] = $retry + 1;
            $queue[] = $task;
            $progress && $progress($task.' · Withings rate limited; retry '.($retry + 1).'/'.$maxRetries.' in '.$delay.' seconds. Other sources can continue.');

            return false;
        };
        try {
            while ($queue || $running) {
                while ($queue && count($running) < $limit) {
                    $index = null;
                    foreach ($queue as $candidate => $task) {
                        if ($blocked || ! str_starts_with($task, 'withings.') || $this->clock() >= $cooldown) {
                            $index = $candidate;
                            break;
                        }
                    }
                    if ($index === null) {
                        break;
                    }
                    $task = $queue[$index];
                    unset($queue[$index]);
                    if ($blocked && str_starts_with($task, 'withings.')) {
                        yield $task => $workflow->failedFetch($id, $owner, $task, ['state' => 'rate_limited', 'message' => 'Withings is still rate limited; the console retry budget was reached. Run again later.', 'entries' => []]);

                        continue;
                    }
                    // Local exports and sequential mode need no child process.
                    if ($limit === 1 || str_starts_with($task, 'apple_health.')) {
                        $status = $workflow->fetch($id, $owner, $task, isset($attempts[$task]));
                        if ($accept($task, $status)) {
                            yield $task => $status;
                        }

                        continue;
                    }
                    try {
                        $command = [PHP_BINARY, base_path('artisan'), 'health:fetch-task', $id, $task, '--no-interaction'];
                        if (isset($attempts[$task])) {
                            $command[] = '--retry-rate-limited';
                        }
                        $running[$task] = $this->processes->path(base_path())->timeout(120)
                            ->env(['HEALTH_PREVIEW_OWNER' => $owner])->start($command);
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
                    if ($accept($task, $status)) {
                        yield $task => $status;
                    }
                }
                if ($running && ! $completed) {
                    usleep(25_000);
                } elseif (! $running && $queue && ! $blocked && $cooldown > $this->clock()
                    && ! array_filter($queue, fn ($task) => ! str_starts_with($task, 'withings.'))) {
                    $remaining = (int) ceil($cooldown - $this->clock());
                    $progress && $progress('Waiting for Withings: '.$remaining.' seconds remaining…');
                    $this->pause(min(30, $remaining));
                }
            }
        } finally {
            foreach ($running as $process) {
                $process->stop(0);
            }
        }
    }

    protected function clock(): float
    {
        return hrtime(true) / 1e9;
    }

    protected function pause(int $seconds): void
    {
        sleep($seconds);
    }
}
