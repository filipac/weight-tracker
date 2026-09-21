<?php

namespace App\Console\Commands;

use App\Health\ConsoleFetcher;
use App\Health\ConsoleRunLock;
use App\Health\MetricCatalog;
use App\Health\ProviderException;
use App\Health\PublishingWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PublishHealthCommand extends Command
{
    protected $signature = 'health:publish
        {--local : Use the local blog for this run instead of the remembered destination}
        {--prod : Use the production blog for this run instead of the remembered destination}
        {--last30 : Fetch today and the previous 29 days from every source (CLI only)}
        {--direct : Publish all selected entries without confirmation or retry prompts}
        {--entry=* : Publish only these topic:YYYY-MM-DD entries (repeatable)}
        {--details : Print every numerical metric and time-series sample in the preview}';

    protected $description = 'Preview and publish today and yesterday (or --last30) to the selected blog';

    public function handle(PublishingWorkflow $workflow, ConsoleFetcher $fetcher): int
    {
        // Large ECG previews can exceed the CLI default while merging existing
        // entries. Keep direct terminal runs consistent with the automation.
        ini_set('memory_limit', '-1');
        if ($this->option('local') && $this->option('prod')) {
            $this->error('Choose either --local or --prod, not both.');

            return self::INVALID;
        }
        $destinationOverride = $this->option('local') ? 'local' : ($this->option('prod') ? 'production' : null);
        if (! $this->option('direct') && ! $this->input->isInteractive()) {
            $this->error('Confirmation requires an interactive terminal. Use --direct for unattended publishing.');

            return self::INVALID;
        }
        $lock = app(ConsoleRunLock::class);
        $id = null;
        $owner = hash('sha256', 'console:'.Str::uuid());
        try {
            if (! $lock->get()) {
                $this->error('Another health:publish command is running. Try again after it finishes.');

                return self::FAILURE;
            }
            $destination = $destinationOverride ?? PublishingWorkflow::rememberedDestination();
            $url = config('health.blogs.'.$destination.'.url');
            $this->info('Destination: '.strtoupper($destination).' · '.$url);
            $this->line('Preparing preview and reading the newest local Apple Health export…');
            $snapshot = $workflow->start($destination, $owner, $this->option('last30') ? 30 : 2);
            $id = $snapshot['id'];
            $this->line('Dates: '.($this->option('last30') ? end($snapshot['dates']).' to '.$snapshot['today'].' (30 days, inclusive)' : $snapshot['today'].' and '.$snapshot['yesterday']).' · '.$snapshot['timezone']);
            $apple = $snapshot['apple_health'];
            $this->line('Apple Health export: '.($apple['folder'] ?? 'No folder'));
            if ($apple['state'] === 'ok') {
                $this->line('Export coverage: '.($apple['from'] ?? 'No dates').' to '.($apple['to'] ?? 'No dates'));
            }
            foreach ($apple['warnings'] ?? [] as $warning) {
                $this->warn($warning);
            }

            $sourceFailures = false;
            $this->line('Fetching up to '.max(1, min(6, (int) config('health.fetch_concurrency', 4))).' source collections at once…');
            foreach ($fetcher->fetch($workflow, $id, $owner, $snapshot['tasks'], fn ($message) => $this->line($message)) as $task => $result) {
                $days = $result['days'] ?: array_map(fn ($date) => ['date' => $date, 'state' => $result['state'], 'message' => $result['message']], $result['checked_dates']);
                foreach ($days as $day) {
                    $this->line(explode('@', $task, 2)[0].' · '.$day['date'].' · '.$day['state'].' · '.$day['message']);
                    if (! in_array($day['state'], ['ok', 'empty', 'unavailable'], true)) {
                        $sourceFailures = true;
                    }
                }
            }
            $this->line('Preparing entry previews and comparing with the blog…');
            $preview = $workflow->prepare($id, $owner);
            if (! $preview['entries']) {
                $this->info('No publishable entries found. Nothing was published.');

                return $sourceFailures ? self::FAILURE : self::SUCCESS;
            }
            $this->showPreview($preview);
            $available = array_keys($preview['entries']);
            $changed = array_keys(array_filter($preview['entries'], fn ($item) => $item['operation'] !== 'unchanged'));
            $selected = array_values(array_unique($this->option('entry')));
            if (array_diff($selected, $available)) {
                $this->error('An --entry value is not in this preview. Use a topic:YYYY-MM-DD key shown above. Nothing was published.');

                return self::INVALID;
            }
            if (! $selected) {
                if (! $changed) {
                    $this->info('All entries are unchanged. Nothing was published.');
                    if ($sourceFailures) {
                        $this->warn('Some source requests failed. Run again after resolving the reported source errors.');
                    }

                    return $sourceFailures ? self::FAILURE : self::SUCCESS;
                }
                $selected = $this->option('direct') ? $changed : $this->choice(
                    'Select entries to publish (comma-separated numbers)',
                    $available,
                    implode(',', array_keys(array_intersect($available, $changed))),
                    null,
                    true
                );
            }
            if (! $this->option('direct') && ! $this->confirm('Publish '.count($selected).' entries to '.strtoupper($destination).' ('.$url.')?', false)) {
                $this->info('Cancelled. Nothing was published.');

                return self::SUCCESS;
            }

            $results = [];
            $publishLimit = max(1, min(6, (int) config('health.publish_concurrency', 4)));
            $this->line('Publishing up to '.$publishLimit.' blog entries at once…');
            do {
                $retry = [];
                foreach (array_chunk($selected, $publishLimit) as $batch) {
                    if ($destinationOverride === null && PublishingWorkflow::rememberedDestination() !== $destination) {
                        $this->error('The destination selected in the web app changed during this run. Run the command again to review the new destination.');

                        return self::FAILURE;
                    }
                    $batchResults = $workflow->publishBatch($id, $owner, $destination, $batch);
                    foreach ($batch as $key) {
                        try {
                            $result = $batchResults[$key];
                            if ($result instanceof \Throwable) {
                                throw $result;
                            }
                            $results[$key] = $result['operation'];
                            $this->info($key.' · '.$result['operation'].' · '.$result['url']);
                        } catch (ProviderException $e) {
                            $results[$key] = 'failed';
                            $this->error($key.' · failed · '.$e->getMessage());
                            if ($e->state !== 'stale') {
                                $retry[] = $key;
                            }
                        } catch (HttpExceptionInterface $e) {
                            $results[$key] = 'failed';
                            $this->error($key.' · failed · '.$e->getMessage());
                            // Expiry, owner and destination guards require a new preview, not a blind retry.
                        } catch (\Throwable) {
                            $results[$key] = 'failed';
                            $this->error($key.' · failed · Publishing could not complete. Retry this entry.');
                            $retry[] = $key;
                        }
                    }
                }
                $selected = $retry;
            } while ($retry && ! $this->option('direct') && $this->confirm('Retry failed entries from this preview?', false));

            $counts = array_count_values($results);
            $this->line('Created: '.($counts['created'] ?? 0).' · Updated: '.($counts['updated'] ?? 0).' · Unchanged: '.($counts['unchanged'] ?? 0).' · Failed: '.($counts['failed'] ?? 0));
            if ($sourceFailures) {
                $this->warn('Some source requests failed. Successful entries were processed; run again after resolving the reported source errors.');
            }

            return $sourceFailures || ! empty($counts['failed']) ? self::FAILURE : self::SUCCESS;
        } catch (ProviderException|HttpExceptionInterface $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\Throwable) {
            $this->error('The health publishing workflow could not complete. Run the command again.');

            return self::FAILURE;
        } finally {
            if ($id) {
                try {
                    $workflow->cancel($id, $owner);
                } catch (\Throwable) { /* Expired snapshots are already removed by the cache. */
                }
            }
            $lock->release();
        }
    }

    private function showPreview(array $preview): void
    {
        $this->newLine();
        $this->info('Review for '.$preview['url']);
        $rows = [];
        foreach ($preview['entries'] as $key => $item) {
            $rows[] = [$key, $item['operation'], implode(', ', array_map(fn ($source) => MetricCatalog::SOURCES[$source], array_keys($item['entry']['providers'])))];
        }
        $this->table(['Entry', 'Action', 'Sources'], $rows);
        foreach ($preview['entries'] as $key => $item) {
            $this->line($key);
            if ($item['retained']) {
                $this->line('Retained: '.implode(', ', $item['retained']));
            }
            foreach ($item['entry']['providers'] as $source => $section) {
                $this->line('  '.MetricCatalog::SOURCES[$source].' · fetched '.$section['fetched_at']);
                $this->showMeasurements($section);
                foreach ($section['workouts'] ?? [] as $workout) {
                    $this->line('  '.MetricCatalog::workoutLabel($workout).' · '.$workout['start'].' to '.$workout['end'].' · '.MetricCatalog::SOURCES[$workout['origin']]);
                    $this->showMeasurements($workout);
                }
            }
        }
        if (! $this->option('details')) {
            $this->line('Use --details to expand all measurements and numerical chart samples.');
        }
        $this->line('Publishing uses this frozen preview without refetching. It expires 30 minutes after fetching began.');
    }

    private function showMeasurements(array $section): void
    {
        $metrics = $this->option('details') ? $section['metrics'] : array_slice($section['metrics'], 0, 3);
        foreach ($metrics as $metric) {
            $value = $metric['unit'] === 'timestamp' ? CarbonImmutable::createFromTimestamp((int) $metric['value'])->setTimezone(config('health.timezone'))->toIso8601String() : $metric['value'].' '.$metric['unit'];
            $this->line('    '.$metric['label'].': '.$value.' · '.$metric['at']);
        }
        if (count($metrics) < count($section['metrics'])) {
            $this->line('    +'.(count($section['metrics']) - count($metrics)).' more measurements');
        }
        foreach ($section['series'] as $series) {
            $values = array_column($series['points'], 'value');
            $this->line('    '.$series['label'].': '.count($values).' samples · '.min($values).'–'.max($values).' '.$series['unit']);
            if ($this->option('details')) {
                foreach ($series['points'] as $point) {
                    $this->line('      '.$point['at'].' · '.$point['value'].' '.$series['unit']);
                }
            }
        }
    }
}
