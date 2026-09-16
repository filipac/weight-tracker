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
        $this->artisan('health:publish', ['--direct' => true])->expectsOutputToContain('Unchanged: 2')->assertExitCode(0);
        $this->assertCount(2, $this->posts);
        $this->assertSame(10, $this->posts['2026-09-15']['id']);
        $this->assertCount(2 * count(Collector::tasks()), $this->fetches);
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
