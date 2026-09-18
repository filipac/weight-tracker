<?php

namespace Tests\Feature\Health;

use App\Health\AppleHealthExport;
use App\Health\Collector;
use App\Health\ConsoleRunLock;
use App\Health\EntryContract;
use App\Health\PublishingWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublishHealthCommandTest extends TestCase
{
    private array $fetches = [];

    private array $posts = [];

    private bool $sourceFailure = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'cache.limiter' => 'array', 'session.driver' => 'array', 'health.fetch_concurrency' => 1,
            'health.console_lock_path' => sys_get_temp_dir().'/health-publish-test-'.bin2hex(random_bytes(8)).'.lock',
            'health.blogs.local.username' => 'local-owner', 'health.blogs.local.password' => 'local-secret',
            'health.blogs.production.username' => 'production-owner', 'health.blogs.production.password' => 'production-secret']);
        Cache::flush();
        Http::preventStrayRequests();
        $this->travelTo(CarbonImmutable::parse('2026-09-16T10:00:00+03:00'));
        $this->mock(AppleHealthExport::class)->shouldReceive('capture')->andReturn(['state' => 'ok', 'folder' => 'fixture-export', 'from' => '2026-09-15', 'to' => '2026-09-16', 'entries' => [], 'warnings' => []]);
        $this->mock(Collector::class)->shouldReceive('fetch')->andReturnUsing(function ($task, $today, $yesterday, $fetchedAt, $apple) {
            $this->fetches[] = [$task, $today, $yesterday, $fetchedAt, $apple];
            if ($this->sourceFailure && $task === 'oura.daily_activity') {
                return ['state' => 'error', 'message' => 'Fixture request failed', 'entries' => []];
            }
            $entries = $task === 'withings.weigh_in' ? ['weight:'.$today => $this->entry($today, 80), 'weight:'.$yesterday => $this->entry($yesterday, 81)] : [];

            return ['state' => $entries ? 'ok' : 'empty', 'message' => 'Fixture', 'entries' => $entries];
        });
        $this->fakeBlog(function ($request) {
            $old = $this->posts[$request['date']] ?? null;
            if ($request->method() === 'GET') {
                return Http::response(['schema_version' => 1, 'entry' => $old['entry'] ?? null, 'revision' => $old['revision'] ?? null]);
            }
            $entry = EntryContract::normalize($request->data());
            $operation = ! $old ? 'created' : (EntryContract::fingerprint($old['entry']) === EntryContract::fingerprint($entry) ? 'unchanged' : 'updated');
            if ($operation !== 'unchanged' && $request['expected_revision'] !== ($old['revision'] ?? null)) {
                return Http::response([], 409);
            }
            $this->posts[$request['date']] = ['entry' => $entry, 'revision' => EntryContract::fingerprint($entry), 'id' => $old['id'] ?? count($this->posts) + 1];

            return Http::response(['schema_version' => 1, 'operation' => $operation, 'id' => $this->posts[$request['date']]['id'], 'url' => 'https://blog.test/health/weight-'.$request['date'].'/', 'revision' => $this->posts[$request['date']]['revision']]);
        });
    }

    protected function tearDown(): void
    {
        @unlink(config('health.console_lock_path'));
        parent::tearDown();
    }

    private function fakeBlog($callback): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake($callback);
    }

    private function entry(string $date, float $weight): array
    {
        return ['schema_version' => 1, 'topic' => 'weight', 'date' => $date, 'timezone' => 'Europe/Bucharest', 'providers' => ['withings' => ['fetched_at' => now()->toIso8601String(), 'metrics' => [['key' => 'withings.measure.1', 'value' => $weight, 'at' => $date.'T08:00:00+03:00']], 'series' => []]]];
    }

    public function test_direct_command_recovers_from_withings_rate_limit_and_publishes_successfully(): void
    {
        config(['health.withings_retry_seconds' => 1]);
        $attempts = 0;
        $this->mock(Collector::class)->shouldReceive('fetch')->andReturnUsing(function ($task, $today, $yesterday) use (&$attempts) {
            if ($task !== 'withings.weigh_in') {
                return ['state' => 'empty', 'message' => 'Fixture', 'entries' => []];
            }
            if (++$attempts === 1) {
                return ['state' => 'rate_limited', 'message' => 'Fixture rate limit', 'entries' => []];
            }

            return ['state' => 'ok', 'message' => 'Recovered', 'entries' => ['weight:'.$today => $this->entry($today, 80)]];
        });
        $this->artisan('health:publish', ['--local' => true, '--direct' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Withings rate limited; retry 1/3 in 1 seconds')
            ->expectsOutputToContain('Created: 1')->assertExitCode(0);
        $this->assertSame(2, $attempts);
        $this->assertCount(1, $this->posts);
    }

    public function test_direct_command_exhausted_rate_limit_still_publishes_other_entries_and_exits_failure(): void
    {
        config(['health.withings_retry_seconds' => 1, 'health.withings_retry_attempts' => 1]);
        $attempts = 0;
        $this->mock(Collector::class)->shouldReceive('fetch')->andReturnUsing(function ($task, $today) use (&$attempts) {
            if ($task === 'withings.heart') {
                $attempts++;

                return ['state' => 'rate_limited', 'message' => 'Fixture rate limit', 'entries' => []];
            }

            return ['state' => 'ok', 'message' => 'Fixture', 'entries' => $task === 'withings.weigh_in' ? ['weight:'.$today => $this->entry($today, 80)] : []];
        });
        $this->artisan('health:publish', ['--local' => true, '--direct' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Withings retry limit reached')
            ->expectsOutputToContain('Created: 1')->assertExitCode(1);
        $this->assertSame(2, $attempts);
        $this->assertCount(1, $this->posts);
    }

    public function test_last30_blog_writes_overlap_with_bounded_concurrency(): void
    {
        config(['health.publish_concurrency' => 3]);
        $active = $peak = $writes = 0;
        Http::globalMiddleware(function ($handler) use (&$active, &$peak, &$writes) {
            return function ($request) use (&$active, &$peak, &$writes) {
                if ($request->getMethod() === 'GET') {
                    return Http::response(['schema_version' => 1, 'entry' => null, 'revision' => null]);
                }
                $this->assertSame('Basic '.base64_encode('production-owner:production-secret'), $request->getHeaderLine('Authorization'));
                $this->assertSame('pacurar.dev', $request->getUri()->getHost());
                $body = json_decode((string) $request->getBody(), true);
                $this->assertNull($body['expected_revision']);
                $peak = max($peak, ++$active);
                $writes++;
                $promise = new \GuzzleHttp\Promise\Promise(function () use (&$promise, &$active, $body) {
                    $active--;
                    $promise->resolve(new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], json_encode([
                        'schema_version' => 1, 'operation' => 'created', 'id' => 1,
                        'url' => 'https://pacurar.dev/health/weight-'.$body['date'], 'revision' => 'fixture',
                    ])));
                });

                return $promise;
            };
        });
        $this->artisan('health:publish', ['--last30' => true, '--prod' => true, '--direct' => true, '--no-interaction' => true])
            ->expectsOutput('Publishing up to 3 blog entries at once…')->expectsOutputToContain('Created: 30')->assertExitCode(0);
        $this->assertSame(3, $peak);
        $this->assertSame(0, $active);
        $this->assertSame(30, $writes);
    }

    public function test_destination_change_during_a_batch_stops_subsequent_batches(): void
    {
        config(['health.publish_concurrency' => 2]);
        $writes = 0;
        $this->fakeBlog(function ($request) use (&$writes) {
            if ($request->method() === 'GET') {
                return Http::response(['schema_version' => 1, 'entry' => null, 'revision' => null]);
            }
            $writes++;
            Cache::forever('health:last_destination', 'production');

            return Http::response(['schema_version' => 1, 'operation' => 'created', 'id' => $writes, 'url' => 'https://blog.test/health/fixture', 'revision' => 'fixture']);
        });
        $this->artisan('health:publish', ['--last30' => true, '--direct' => true])->expectsOutputToContain('destination selected in the web app changed')->assertExitCode(1);
        $this->assertSame(2, $writes); // Already dispatched entries finish; no next batch is sent.
        Http::assertNotSent(fn ($request) => ! str_starts_with($request->url(), 'https://blog.test/'));
    }

    public function test_last30_checks_each_source_for_exactly_thirty_dates_and_upserts_without_duplicates(): void
    {
        $old = EntryContract::normalize($this->entry('2026-08-18', 90));
        $this->posts['2026-08-18'] = ['id' => 99, 'entry' => $old, 'revision' => EntryContract::fingerprint($old)];
        $this->artisan('health:publish', ['--last30' => true, '--local' => true, '--direct' => true, '--no-interaction' => true])
            ->expectsOutput('Dates: 2026-08-18 to 2026-09-16 (30 days, inclusive) · Europe/Bucharest')
            ->expectsOutputToContain('Created: 29 · Updated: 1')->assertExitCode(0);
        $this->assertCount(30, $this->posts);
        $this->assertSame(99, $this->posts['2026-08-18']['id']);
        $this->assertCount(15 * count(Collector::tasks()), $this->fetches);
        $expected = array_map(fn ($offset) => CarbonImmutable::parse('2026-09-16')->subDays($offset)->toDateString(), range(0, 29));
        foreach (Collector::tasks() as $task) {
            $actual = [];
            foreach ($this->fetches as [$name, $today, $yesterday, $at, $apple]) {
                if ($name !== $task) {
                    continue;
                }
                array_push($actual, $today, $yesterday);
                if (str_starts_with($task, 'apple_health.')) {
                    $this->assertSame('fixture-export', $apple['folder']);
                }
            }
            $this->assertSame($expected, $actual, $task);
        }
        $this->artisan('health:publish', ['--last30' => true, '--direct' => true])
            ->expectsOutput('All entries are unchanged. Nothing was published.')->assertExitCode(0);
        $this->assertCount(30, Http::recorded(fn ($r) => $r->method() === 'POST'));
        $this->assertCount(30, $this->posts);
    }

    public function test_last30_can_be_cancelled_before_any_publication(): void
    {
        $this->artisan('health:publish', ['--last30' => true, '--entry' => ['weight:2026-08-18']])
            ->expectsConfirmation('Publish 1 entries to LOCAL (https://blog.test)?', 'no')
            ->expectsOutput('Cancelled. Nothing was published.')->assertExitCode(0);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
    }

    public function test_last30_partial_failure_does_not_block_successful_historical_entries(): void
    {
        $this->sourceFailure = true;
        $this->artisan('health:publish', ['--last30' => true, '--direct' => true])
            ->expectsOutputToContain('Some source requests failed')->assertExitCode(1);
        $this->assertCount(30, $this->posts);
        $this->assertArrayHasKey('2026-08-18', $this->posts);
    }

    public function test_last30_real_collector_merges_old_withings_oura_and_apple_results(): void
    {
        $oldest = '2026-08-18';
        $oura = $this->mock(\App\Health\OuraClient::class);
        $oura->shouldReceive('collection')->andReturnUsing(fn ($endpoint, $params) => $endpoint === 'daily_activity' && $params['start_date'] === $oldest ? [['day' => $oldest, 'steps' => 101]] : []);
        $withings = $this->mock(\App\Health\WithingsClient::class);
        $withings->shouldReceive('collection')->andReturnUsing(fn ($path, $params) => $params['action'] === 'getactivity' && $params['startdateymd'] === $oldest ? [['date' => $oldest, 'steps' => 102]] : []);
        $measurements = $this->mock(\App\Health\WithingsMeasurements::class);
        $measurements->shouldReceive('forDay')->andReturn([]);
        $measurements->shouldReceive('minimum')->andReturn(null);
        Cache::forever('withings', ['scopes' => ['user.activity']]);
        $this->app->instance(Collector::class, new Collector($oura, $withings, $measurements));
        $apple = \Mockery::mock(AppleHealthExport::class)->makePartial();
        $apple->shouldReceive('capture')->once()->andReturn([
            'state' => 'ok', 'folder' => 'historical-export', 'from' => $oldest, 'to' => '2026-09-16',
            'entries' => ['activity' => [$oldest => ['schema_version' => 1, 'topic' => 'activity', 'date' => $oldest, 'timezone' => 'Europe/Bucharest',
                'providers' => ['apple_health' => ['fetched_at' => now()->toIso8601String(), 'series' => [], 'metrics' => [
                    ['key' => 'apple_health.step_count', 'value' => 103, 'at' => $oldest.'T08:00:00+03:00'],
                ]]]]]],
        ]);
        $this->app->instance(AppleHealthExport::class, $apple);
        $this->artisan('health:publish', ['--last30' => true, '--direct' => true])->assertExitCode(0);
        $this->assertSame([$oldest], array_keys($this->posts));
        $providers = $this->posts[$oldest]['entry']['providers'];
        $this->assertSame(['apple_health', 'oura', 'withings'], array_keys($providers));
        foreach (['oura' => 101.0, 'withings' => 102.0, 'apple_health' => 103.0] as $source => $value) {
            $this->assertSame($value, $providers[$source]['metrics'][0]['value']);
        }
    }

    public function test_web_preview_stays_at_two_days_even_when_last30_is_submitted(): void
    {
        $response = $this->postJson('/health/previews', ['destination' => 'local', 'last30' => true, 'days' => 30])->assertOk()->json();
        $this->assertSame(['2026-09-16', '2026-09-15'], $response['dates']);
        $this->assertSame(Collector::tasks(), $response['tasks']);
        foreach ($response['task_dates'] as $dates) {
            $this->assertSame(['2026-09-16', '2026-09-15'], $dates);
        }
        Http::assertNothingSent();
    }

    public function test_historical_snapshot_freezes_calendar_dates_across_dst_and_cleans_up_all_batches(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25T23:59:00+02:00'));
        $workflow = app(PublishingWorkflow::class);
        $snapshot = $workflow->start('local', 'historical-owner', 30);
        $this->assertCount(30, array_unique($snapshot['dates']));
        $this->assertSame('2026-09-26', $snapshot['dates'][29]);
        $this->travel(2)->minutes();
        $task = 'withings.weigh_in@2026-09-27';
        $status = $workflow->fetch($snapshot['id'], 'historical-owner', $task);
        $this->assertSame(['2026-09-27', '2026-09-26'], $status['checked_dates']);
        $this->assertSame(['withings.weigh_in', '2026-09-27', '2026-09-26'], array_slice($this->fetches[0], 0, 3));
        foreach ($snapshot['tasks'] as $task) {
            $workflow->failedFetch($snapshot['id'], 'historical-owner', $task);
        }
        $this->assertTrue(Cache::has('health:task:'.$snapshot['id'].':oura.sleep@2026-09-27'));
        $workflow->cancel($snapshot['id'], 'historical-owner');
        foreach ($snapshot['tasks'] as $task) {
            $this->assertNull(Cache::get('health:task:'.$snapshot['id'].':'.$task));
        }
    }

    public function test_cancelled_interactive_preview_never_publishes(): void
    {
        $this->artisan('health:publish')
            ->expectsChoice('Select entries to publish (comma-separated numbers)', ['weight:2026-09-16'], ['weight:2026-09-16', 'weight:2026-09-15'])
            ->expectsConfirmation('Publish 1 entries to LOCAL (https://blog.test)?', 'no')
            ->expectsOutput('Cancelled. Nothing was published.')->assertExitCode(0);
        $this->assertSame([], $this->posts);
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
        $lock = app(ConsoleRunLock::class);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_confirmed_selection_only_publishes_reviewed_entry(): void
    {
        $this->artisan('health:publish', ['--entry' => ['weight:2026-09-16'], '--details' => true])
            ->expectsConfirmation('Publish 1 entries to LOCAL (https://blog.test)?', 'yes')
            ->expectsOutputToContain('Created: 1')->assertExitCode(0);
        $this->assertSame(['2026-09-16'], array_keys($this->posts));
        $this->assertCount(count(Collector::tasks()), $this->fetches);
        $this->assertSame(80.0, $this->posts['2026-09-16']['entry']['providers']['withings']['metrics'][0]['value']);
    }

    public function test_direct_uses_web_cached_destination_and_correct_credentials_without_prompts(): void
    {
        $this->postJson('/health/destination', ['destination' => 'production'])->assertNoContent();
        $this->artisan('health:publish', ['--direct' => true, '--no-interaction' => true])
            ->expectsOutput('Destination: PRODUCTION · https://pacurar.dev')->assertExitCode(0);
        Http::assertSentCount(4);
        Http::assertNotSent(fn ($r) => ! str_starts_with($r->url(), 'https://pacurar.dev/') || ! $r->hasHeader('Authorization', 'Basic '.base64_encode('production-owner:production-secret')));
        $this->assertCount(2, $this->posts);
        foreach ($this->fetches as [$task, $today, $yesterday, $at, $apple]) {
            $this->assertSame('2026-09-16', $today);
            $this->assertSame('2026-09-15', $yesterday);
            if (str_starts_with($task, 'apple_health.')) {
                $this->assertSame('fixture-export', $apple['folder']);
            }
        }
        $this->assertSame('production', PublishingWorkflow::rememberedDestination());
    }

    public function test_local_override_uses_local_credentials_without_changing_cached_destination(): void
    {
        Cache::forever('health:last_destination', 'production');
        $this->artisan('health:publish', ['--local' => true, '--direct' => true, '--no-interaction' => true])
            ->expectsOutput('Destination: LOCAL · https://blog.test')->assertExitCode(0);
        Http::assertSentCount(4);
        Http::assertNotSent(fn ($r) => ! str_starts_with($r->url(), 'https://blog.test/') || ! $r->hasHeader('Authorization', 'Basic '.base64_encode('local-owner:local-secret')));
        $this->assertSame('production', PublishingWorkflow::rememberedDestination());
    }

    public function test_prod_override_uses_production_credentials_without_changing_cached_destination(): void
    {
        Cache::forever('health:last_destination', 'local');
        $this->artisan('health:publish', ['--prod' => true, '--direct' => true, '--no-interaction' => true])
            ->expectsOutput('Destination: PRODUCTION · https://pacurar.dev')->assertExitCode(0);
        Http::assertSentCount(4);
        Http::assertNotSent(fn ($r) => ! str_starts_with($r->url(), 'https://pacurar.dev/') || ! $r->hasHeader('Authorization', 'Basic '.base64_encode('production-owner:production-secret')));
        $this->assertSame('local', PublishingWorkflow::rememberedDestination());
    }

    public function test_conflicting_destination_flags_fail_before_fetching(): void
    {
        $this->artisan('health:publish', ['--local' => true, '--prod' => true, '--direct' => true])
            ->expectsOutput('Choose either --local or --prod, not both.')->assertExitCode(2);
        $this->assertSame([], $this->fetches);
        Http::assertNothingSent();
    }

    public function test_explicit_destination_remains_fixed_when_web_selection_changes(): void
    {
        Cache::forever('health:last_destination', 'local');
        $this->fakeBlog(function ($r) {
            Cache::forever('health:last_destination', 'production');

            return $r->method() === 'GET'
                ? Http::response(['schema_version' => 1, 'entry' => null])
                : Http::response(['schema_version' => 1, 'operation' => 'created', 'id' => 1, 'url' => 'https://blog.test/health/fixture', 'revision' => str_repeat('a', 64)]);
        });
        $this->artisan('health:publish', ['--local' => true, '--direct' => true])->assertExitCode(0);
        $this->assertCount(2, Http::recorded(fn ($r) => $r->method() === 'POST'));
        Http::assertNotSent(fn ($r) => ! str_starts_with($r->url(), 'https://blog.test/'));
    }

    public function test_plain_command_removes_memory_limit_before_loading_exports(): void
    {
        $previous = ini_get('memory_limit');
        ini_set('memory_limit', '128M');
        $this->app->instance(AppleHealthExport::class, new class extends AppleHealthExport
        {
            public function capture(string $fetchedAt): array
            {
                \PHPUnit\Framework\Assert::assertSame('-1', ini_get('memory_limit'));

                return ['state' => 'unavailable', 'entries' => []];
            }
        });
        try {
            $this->artisan('health:publish', ['--direct' => true, '--no-interaction' => true])->assertExitCode(0);
            $this->assertSame('-1', ini_get('memory_limit'));
        } finally {
            ini_set('memory_limit', $previous);
        }
    }

    public function test_no_interaction_requires_explicit_direct_before_fetching(): void
    {
        $this->artisan('health:publish', ['--no-interaction' => true])->expectsOutputToContain('Use --direct')->assertExitCode(2);
        $this->assertSame([], $this->fetches);
        Http::assertNothingSent();
    }

    public function test_direct_updates_existing_entry_then_second_run_is_unchanged(): void
    {
        $old = EntryContract::normalize($this->entry('2026-09-15', 90));
        $this->posts['2026-09-15'] = ['id' => 10, 'entry' => $old, 'revision' => EntryContract::fingerprint($old)];
        $this->artisan('health:publish', ['--direct' => true])->expectsOutputToContain('Created: 1 · Updated: 1')->assertExitCode(0);
        $this->artisan('health:publish', ['--direct' => true])->expectsOutput('All entries are unchanged. Nothing was published.')->assertExitCode(0);
        $this->assertCount(2, Http::recorded(fn ($request) => $request->method() === 'POST'));
        $this->assertCount(2, $this->posts);
        $this->assertSame(10, $this->posts['2026-09-15']['id']);
        $this->assertCount(2 * count(Collector::tasks()), $this->fetches);
    }

    public function test_direct_skips_unchanged_entries_in_a_mixed_preview(): void
    {
        $old = EntryContract::normalize($this->entry('2026-09-16', 80));
        $this->posts['2026-09-16'] = ['id' => 10, 'entry' => $old, 'revision' => EntryContract::fingerprint($old)];
        $this->artisan('health:publish', ['--direct' => true])->assertExitCode(0);
        $this->assertCount(1, Http::recorded(fn ($r) => $r->method() === 'POST'));
        Http::assertNotSent(fn ($r) => $r->method() === 'POST' && $r['date'] === '2026-09-16');
    }

    public function test_explicit_entry_can_include_an_unchanged_entry(): void
    {
        $old = EntryContract::normalize($this->entry('2026-09-16', 80));
        $this->posts['2026-09-16'] = ['id' => 10, 'entry' => $old, 'revision' => EntryContract::fingerprint($old)];
        $this->artisan('health:publish', ['--direct' => true, '--entry' => ['weight:2026-09-16']])
            ->expectsOutputToContain('Unchanged: 1')->assertExitCode(0);
        $this->assertCount(1, Http::recorded(fn ($r) => $r->method() === 'POST'));
        Http::assertNotSent(fn ($r) => $r->method() === 'POST' && $r['date'] === '2026-09-15');
    }

    public function test_interactive_run_with_all_entries_unchanged_exits_without_prompts(): void
    {
        foreach (['2026-09-16' => 80, '2026-09-15' => 81] as $date => $value) {
            $old = EntryContract::normalize($this->entry($date, $value));
            $this->posts[$date] = ['id' => count($this->posts) + 1, 'entry' => $old, 'revision' => EntryContract::fingerprint($old)];
        }
        $this->artisan('health:publish')->expectsOutput('All entries are unchanged. Nothing was published.')->assertExitCode(0);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
    }

    public function test_partial_sources_still_publish_successes_but_exit_nonzero(): void
    {
        $this->sourceFailure = true;
        $this->artisan('health:publish', ['--direct' => true])->expectsOutputToContain('Some source requests failed')->assertExitCode(1);
        $this->assertCount(2, $this->posts);
    }

    public function test_stale_entry_does_not_block_other_entry_or_get_retried_blindly(): void
    {
        $this->fakeBlog(fn ($r) => $r->method() === 'GET' ? Http::response(['schema_version' => 1, 'entry' => null, 'revision' => null]) : ($r['date'] === '2026-09-16' ? Http::response([], 409) : Http::response(['schema_version' => 1, 'operation' => 'created', 'id' => 2, 'url' => 'https://blog.test/health/fixture', 'revision' => str_repeat('a', 64)])));
        $this->artisan('health:publish', ['--direct' => true])->expectsOutputToContain('Created: 1 · Updated: 0 · Unchanged: 0 · Failed: 1')->assertExitCode(1);
        Http::assertSentCount(4);
    }

    public function test_interactive_retry_uses_same_snapshot_without_refetching(): void
    {
        $this->fakeBlog(['blog.test/*' => Http::sequence()->push(['schema_version' => 1, 'entry' => null])->push(['schema_version' => 1, 'entry' => null])
            ->push([], 503)->push(['schema_version' => 1, 'operation' => 'created', 'id' => 1, 'url' => 'https://blog.test/health/fixture', 'revision' => str_repeat('a', 64)])]);
        $this->artisan('health:publish', ['--entry' => ['weight:2026-09-16']])
            ->expectsConfirmation('Publish 1 entries to LOCAL (https://blog.test)?', 'yes')
            ->expectsConfirmation('Retry failed entries from this preview?', 'yes')->assertExitCode(0);
        $this->assertCount(count(Collector::tasks()), $this->fetches);
        Http::assertSentCount(4);
        $writes = Http::recorded(fn ($r) => $r->method() === 'POST')->values();
        $this->assertSame($writes[0][0]->data(), $writes[1][0]->data());
    }

    public function test_destination_change_after_fetch_prevents_publication(): void
    {
        $this->fakeBlog(function ($r) {
            Cache::forever('health:last_destination', 'production');

            return Http::response(['schema_version' => 1, 'entry' => null]);
        });
        $this->artisan('health:publish', ['--direct' => true])->expectsOutputToContain('destination selected in the web app changed')->assertExitCode(1);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
    }

    public function test_changed_credentials_and_expired_previews_are_not_published(): void
    {
        $this->fakeBlog(function ($r) {
            config(['health.blogs.local.password' => 'changed-secret']);

            return Http::response(['schema_version' => 1, 'entry' => null]);
        });
        $this->artisan('health:publish', ['--direct' => true])->expectsOutputToContain('Destination configuration changed')->assertExitCode(1);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
        $this->fakeBlog(function ($r) {
            $this->travel(31)->minutes();

            return Http::response(['schema_version' => 1, 'entry' => null]);
        });
        $this->artisan('health:publish', ['--direct' => true])->expectsOutputToContain('This preview expired')->assertExitCode(1);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
    }

    public function test_overlap_and_unknown_selection_never_publish(): void
    {
        $lock = app(ConsoleRunLock::class);
        $lock->get();
        $this->artisan('health:publish', ['--direct' => true])->expectsOutputToContain('Another health:publish command is running')->assertExitCode(1);
        $lock->release();
        Http::assertNothingSent();
        $this->artisan('health:publish', ['--direct' => true, '--entry' => ['weight:1999-01-01']])->assertExitCode(2);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
    }

    public function test_abandoned_legacy_cache_lock_does_not_block_a_new_run(): void
    {
        Cache::lock('health:console:publish', 3600)->get();
        $this->artisan('health:publish', ['--entry' => ['weight:2026-09-16']])
            ->expectsConfirmation('Publish 1 entries to LOCAL (https://blog.test)?', 'no')
            ->expectsOutput('Cancelled. Nothing was published.')->assertExitCode(0);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
    }

    public function test_console_and_web_owners_cannot_use_each_others_previews(): void
    {
        $workflow = app(PublishingWorkflow::class);
        $snapshot = $workflow->start('local', 'console-fixture-owner');
        try {
            $workflow->prepare($snapshot['id'], 'another-owner');
            $this->fail('Owner must be checked.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            $this->assertSame(410, $e->getStatusCode());
        }
        $workflow->cancel($snapshot['id'], 'console-fixture-owner');
        $this->assertNull(Cache::get('health:preview:'.$snapshot['id']));
        $this->assertNull(Cache::get('health:apple:'.$snapshot['id']));
        Http::assertNothingSent();
    }
}
