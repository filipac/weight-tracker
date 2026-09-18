<?php

namespace Tests\Feature\Health;

use App\Health\AppleHealthExport;
use App\Health\BlogClient;
use App\Health\OuraClient;
use App\Health\ProviderException;
use App\Health\PublishingWorkflow;
use App\Health\WithingsClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParallelPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'health.fetch_concurrency' => 4,
            'health.blogs.local.username' => 'local-owner', 'health.blogs.local.password' => 'local-secret',
            'health.blogs.production.username' => 'production-owner', 'health.blogs.production.password' => 'production-secret']);
        Http::preventStrayRequests();
    }

    public function test_parallel_blog_reads_preserve_identity_and_destination_credentials(): void
    {
        Http::fake(fn ($request) => Http::response(['schema_version' => 1,
            'revision' => $request['topic'].':'.$request['date'], 'entry' => null]));
        $entries = [];
        foreach (['weight', 'activity', 'sleep'] as $topic) {
            foreach (['2026-09-16', '2026-09-15'] as $date) {
                $entries[$topic.':'.$date] = compact('topic', 'date');
            }
        }
        $results = app(BlogClient::class)->readMany('production', $entries);
        $this->assertSame(array_keys($entries), array_keys($results));
        foreach ($results as $key => $result) {
            $this->assertSame($key, $result['revision']);
        }
        Http::assertSentCount(6);
        Http::assertNotSent(fn ($r) => $r->method() !== 'GET' || ! str_starts_with($r->url(), 'https://pacurar.dev/')
            || ! $r->hasHeader('Authorization', 'Basic '.base64_encode('production-owner:production-secret')));
    }

    public function test_a_rejected_blog_read_does_not_become_a_missing_entry(): void
    {
        Http::fake(fn ($r) => $r['topic'] === 'sleep' ? Http::response([], 403)
            : Http::response(['schema_version' => 1, 'entry' => null]));
        try {
            app(BlogClient::class)->readMany('local', [
                'weight' => ['topic' => 'weight', 'date' => '2026-09-16'],
                'sleep' => ['topic' => 'sleep', 'date' => '2026-09-16'],
            ]);
            $this->fail('A failed read must prevent an unsafe create preview.');
        } catch (ProviderException $e) {
            $this->assertSame('authentication', $e->state);
        }
        Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
    }

    public function test_blog_reads_overlap_without_exceeding_the_configured_limit(): void
    {
        $active = 0;
        $peak = 0;
        // Http::fake eagerly waits on promises; intercept the transport instead
        // so requests remain pending while the pool fills. No network is used.
        Http::globalMiddleware(function ($handler) use (&$active, &$peak) {
            return function () use (&$active, &$peak) {
                $peak = max($peak, ++$active);
                $promise = new \GuzzleHttp\Promise\Promise(function () use (&$promise, &$active) {
                    $active--;
                    $promise->resolve(new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'application/json'], '{"schema_version":1,"entry":null}'));
                });

                return $promise;
            };
        });
        $entries = array_fill(0, 9, ['topic' => 'weight', 'date' => '2026-09-16']);
        $this->assertCount(9, app(BlogClient::class)->readMany('local', $entries));
        $this->assertSame(4, $peak);
        $this->assertSame(0, $active);
    }

    public function test_parallel_writes_keep_successes_when_sibling_requests_fail(): void
    {
        Http::fake(fn ($request) => match ($request['topic']) {
            'heart' => Http::failedConnection(),
            'sleep' => Http::response([], 409),
            'activity' => Http::response([], 503),
            default => Http::response(['schema_version' => 1, 'operation' => 'updated', 'url' => 'https://pacurar.dev/health/fixture', 'revision' => 'new']),
        });
        $items = [];
        foreach (['heart', 'weight', 'sleep', 'activity'] as $topic) {
            $items[$topic] = ['entry' => ['topic' => $topic, 'date' => '2026-09-16'], 'expected_revision' => 'reviewed-'.$topic];
        }
        $results = app(BlogClient::class)->publishMany('production', $items);
        $this->assertCount(4, $results);
        $this->assertSame('connection', $results['heart']->state);
        $this->assertSame('updated', $results['weight']['operation']);
        $this->assertSame('stale', $results['sleep']->state);
        $this->assertSame('error', $results['activity']->state);
        Http::assertNotSent(fn ($r) => $r['expected_revision'] !== 'reviewed-'.$r['topic']
            || ! $r->hasHeader('Authorization', 'Basic '.base64_encode('production-owner:production-secret')));
    }

    public function test_batch_locks_are_independent_and_released_after_partial_failure(): void
    {
        config(['health.publish_concurrency' => 4]);
        $this->mock(AppleHealthExport::class)->shouldReceive('capture')->andReturn(['state' => 'empty', 'entries' => []]);
        $workflow = app(PublishingWorkflow::class);
        $snapshot = $workflow->start('local', 'owner');
        $items = [];
        foreach (['heart', 'weight', 'sleep'] as $topic) {
            $items[$topic] = ['entry' => ['topic' => $topic, 'date' => $snapshot['today']], 'expected_revision' => 'reviewed'];
        }
        Cache::put('health:prepared:'.$snapshot['id'], ['entries' => $items], 60);
        $busy = Cache::lock('health:publish:'.$snapshot['id'].':'.sha1('heart'), 30);
        $this->assertTrue($busy->get());
        Http::fake(fn ($r) => $r['topic'] === 'sleep' ? Http::response([], 503) : Http::response(['schema_version' => 1, 'operation' => 'created']));
        try {
            $results = $workflow->publishBatch($snapshot['id'], 'owner', 'local', array_keys($items));
            $this->assertSame('busy', $results['heart']->state);
            $this->assertSame('created', $results['weight']['operation']);
            $this->assertSame('error', $results['sleep']->state);
            $this->assertFalse(Cache::lock('health:publish:'.$snapshot['id'].':'.sha1('heart'), 30)->get());
            foreach (['weight', 'sleep'] as $topic) {
                $lock = Cache::lock('health:publish:'.$snapshot['id'].':'.sha1($topic), 30);
                $this->assertTrue($lock->get());
                $lock->release();
            }
            Http::assertSentCount(2);
            Http::assertNotSent(fn ($r) => $r['topic'] === 'heart');
        } finally {
            $busy->release();
        }
    }

    public function test_valid_provider_tokens_do_not_wait_for_refresh_locks(): void
    {
        app(OuraClient::class)->save(['access_token' => 'oura-token', 'refresh_token' => 'oura-refresh', 'expires_in' => 3600], ['daily']);
        Cache::forever('withings', ['access_token' => 'withings-token', 'refresh_token' => 'withings-refresh', 'expires_at' => now()->addHour()->timestamp]);
        $oura = Cache::lock('health:oura:refresh', 30);
        $withings = Cache::lock('health:withings:refresh', 30);
        $this->assertTrue($oura->get());
        $this->assertTrue($withings->get());
        try {
            $this->assertSame('oura-token', app(OuraClient::class)->accessToken());
            $this->assertSame('withings-token', app(WithingsClient::class)->token());
        } finally {
            $oura->release();
            $withings->release();
        }
        Http::assertNothingSent();
    }

    public function test_withings_refresh_rotates_and_reuses_the_new_credentials(): void
    {
        Cache::forever('withings', ['access_token' => 'old', 'refresh_token' => 'old-refresh', 'expires_at' => now()->timestamp]);
        Http::fake(['wbsapi.withings.net/v2/oauth2' => Http::response(['status' => 0, 'body' => [
            'access_token' => 'new', 'refresh_token' => 'new-refresh', 'expires_in' => 3600,
        ]])]);
        $this->assertSame('new', app(WithingsClient::class)->token());
        $this->assertSame('new', app(WithingsClient::class)->token());
        $this->assertSame('new-refresh', Cache::get('withings')['refresh_token']);
        Http::assertSentCount(1);
    }
}
