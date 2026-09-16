<?php

namespace Tests\Feature\Health;

use App\Health\AppleHealthExport;
use App\Health\Collector;
use App\Health\ConsoleFetcher;
use App\Health\PublishingWorkflow;
use Illuminate\Process\Factory;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConsoleFetcherTest extends TestCase
{
    private string $directory;

    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/health-console-test-'.Str::uuid();
        mkdir($this->directory);
        $this->owner = hash('sha256', 'test-owner');
        config(['cache.default' => 'file', 'cache.stores.file.path' => $this->directory,
            'health.console_lock_path' => $this->directory.'/publish.lock',
            'cache.stores.file.lock_path' => $this->directory, 'health.fetch_concurrency' => 4,
            'health.blogs.local.username' => 'fixture', 'health.blogs.local.password' => 'fixture']);
        Http::preventStrayRequests();
        Cache::put('test:barrier', 4, 60);
        $this->mock(AppleHealthExport::class)->shouldReceive('capture')->andReturn(['state' => 'empty', 'entries' => []]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function fetcher(): ConsoleFetcher
    {
        // Keep the production process scheduler, replacing only child bootstrap
        // with a fake collector and an isolated cache shared by test processes.
        $factory = new class($this->directory) extends Factory
        {
            public function __construct(private string $directory) {}

            public function newPendingProcess()
            {
                return new class($this, $this->directory) extends PendingProcess
                {
                    public function __construct(Factory $factory, private string $directory)
                    {
                        parent::__construct($factory);
                    }

                    public function start(array|string|null $command = null, ?callable $output = null)
                    {
                        $command[1] = base_path('tests/health/console-fetch-worker.php');
                        $this->env($this->environment + ['APP_ENV' => 'testing', 'HEALTH_TEST_CACHE' => $this->directory]);

                        return parent::start($command, $output);
                    }
                };
            }
        };

        return new ConsoleFetcher($factory);
    }

    public function test_real_processes_overlap_and_share_frozen_dates_and_cached_results(): void
    {
        $workflow = app(PublishingWorkflow::class);
        $snapshot = $workflow->start('local', $this->owner);
        $tasks = array_slice(Collector::tasks(), 0, 6);
        $results = iterator_to_array($this->fetcher()->fetch($workflow, $snapshot['id'], $this->owner, $tasks));
        $this->assertCount(6, $results);
        $this->assertSame(4, Cache::get('test:peak'));
        $this->assertSame(0, Cache::get('test:active'));
        $calls = Cache::get('test:calls');
        $this->assertCount(6, $calls);
        $this->assertCount(6, array_unique(array_column($calls, 0)));
        foreach ($calls as [$task, $today, $yesterday, $fetchedAt]) {
            $this->assertSame($snapshot['today'], $today);
            $this->assertSame($snapshot['yesterday'], $yesterday);
            $this->assertSame('empty', $results[$task]['state']);
            $this->assertSame('empty', Cache::get('health:task:'.$snapshot['id'].':'.$task)['state']);
        }
        $this->assertCount(1, array_unique(array_column($calls, 3)));
        Http::assertNothingSent();
    }

    public function test_worker_crashes_and_provider_errors_do_not_block_successful_tasks_or_preview(): void
    {
        $workflow = app(PublishingWorkflow::class);
        $snapshot = $workflow->start('local', $this->owner);
        Cache::put('test:crash', 'withings.activity', 60);
        Cache::put('test:failure', 'withings.sleep', 60);
        $tasks = array_slice(Collector::tasks(), 0, 6);
        $results = iterator_to_array($this->fetcher()->fetch($workflow, $snapshot['id'], $this->owner, $tasks));
        $this->assertSame('error', $results['withings.activity']['state']);
        $this->assertSame('rate_limited', $results['withings.sleep']['state']);
        $this->assertSame('empty', $results['withings.weigh_in']['state']);
        foreach (array_diff(Collector::tasks(), $tasks) as $task) {
            $workflow->failedFetch($snapshot['id'], $this->owner, $task);
        }
        $preview = $workflow->prepare($snapshot['id'], $this->owner);
        $this->assertSame('empty', $preview['statuses']['withings.weigh_in']['state']);
        $this->assertSame('error', $preview['statuses']['withings.activity']['state']);
        $this->assertSame([], $preview['entries']);
        Http::assertNothingSent();
    }

    public function test_failed_worker_does_not_overwrite_a_result_that_was_already_cached(): void
    {
        $workflow = app(PublishingWorkflow::class);
        $snapshot = $workflow->start('local', $this->owner);
        Cache::put('health:task:'.$snapshot['id'].':oura.daily_activity', ['state' => 'ok', 'message' => 'Completed', 'entries' => []], 60);
        $this->assertSame('ok', $workflow->failedFetch($snapshot['id'], $this->owner, 'oura.daily_activity')['state']);
        $workflow->cancel($snapshot['id'], $this->owner);
        try {
            $workflow->failedFetch($snapshot['id'], $this->owner, 'oura.daily_activity');
            $this->fail('Cancelled previews cannot be recreated by a failed worker.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(410, $e->getStatusCode());
        }
    }

    public function test_console_reviews_parallel_results_and_cancellation_never_publishes(): void
    {
        $today = now(config('health.timezone'))->toDateString();
        $entry = ['schema_version' => 1, 'topic' => 'weight', 'date' => $today, 'timezone' => config('health.timezone'),
            'providers' => ['withings' => ['fetched_at' => now()->toIso8601String(), 'metrics' => [
                ['key' => 'withings.measure.1', 'value' => 80, 'at' => $today.'T08:00:00+03:00'],
            ], 'series' => []]]];
        Cache::put('test:entries', ['weight:'.$today => $entry], 60);
        Cache::forever('health:last_destination', 'local');
        $this->app->instance(ConsoleFetcher::class, $this->fetcher());
        $this->mock(Collector::class)->shouldReceive('fetch')->times(8)
            ->andReturn(['state' => 'empty', 'message' => 'Local Apple Health fixture', 'entries' => []]);
        Http::fake(['blog.test/*' => Http::response(['schema_version' => 1, 'entry' => null, 'revision' => null])]);
        $this->artisan('health:publish', ['--entry' => ['weight:'.$today]])
            ->expectsOutput('Fetching up to 4 source collections at once…')
            ->expectsConfirmation('Publish 1 entries to LOCAL (https://blog.test)?', 'no')
            ->expectsOutput('Cancelled. Nothing was published.')->assertExitCode(0);
        $this->assertSame(4, Cache::get('test:peak'));
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
    }
}
