<?php

namespace Tests\Feature\Health;

use App\Health\AppleHealthExport;
use App\Health\ConsoleFetcher;
use App\Health\ProviderException;
use App\Health\PublishingWorkflow;
use App\Health\WithingsClient;
use Illuminate\Process\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WithingsRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'health.fetch_concurrency' => 1,
            'health.blogs.local.username' => 'fixture', 'health.blogs.local.password' => 'fixture',
            'health.withings_retry_attempts' => 3, 'health.withings_retry_seconds' => 60,
            'health.withings_retry_budget_seconds' => 600]);
        Cache::flush();
        Http::preventStrayRequests();
        Cache::put('withings', ['access_token' => 'fixture', 'refresh_token' => 'fixture', 'expires_at' => now()->timestamp + 3600]);
        $this->mock(AppleHealthExport::class)->shouldReceive('capture')->andReturn(['state' => 'empty', 'entries' => []]);
    }

    private function fetcher(): ConsoleFetcher
    {
        return new class(app(Factory::class)) extends ConsoleFetcher
        {
            public float $time = 0;

            public array $waits = [];

            protected function clock(): float
            {
                return $this->time;
            }

            protected function pause(int $seconds): void
            {
                $this->waits[] = $seconds;
                $this->time += $seconds;
            }
        };
    }

    public function test_http_and_withings_rate_limits_preserve_retry_after_seconds_and_dates(): void
    {
        Http::fakeSequence()->push([], 429, ['Retry-After' => '90'])
            ->push(['status' => '601'], 200, ['Retry-After' => now()->addSeconds(120)->toRfc7231String()])
            ->push(['status' => 601], 200, ['Retry-After' => 'invalid']);
        foreach ([90, 120, null] as $delay) {
            try {
                app(WithingsClient::class)->request('measure', []);
                $this->fail('Expected a rate limit');
            } catch (ProviderException $e) {
                $this->assertSame('rate_limited', $e->state);
                $this->assertSame($delay, $e->retryAfter);
            }
        }
    }

    public function test_retry_preserves_successful_day_and_only_refetches_limited_day(): void
    {
        $workflow = app(PublishingWorkflow::class);
        $snapshot = $workflow->start('local', 'owner');
        $group = ['category' => 1, 'date' => now(config('health.timezone'))->startOfDay()->addHours(8)->timestamp,
            'measures' => [['type' => 1, 'value' => 80000, 'unit' => -3]]];
        Http::fakeSequence()->push(['status' => 0, 'body' => ['measuregrps' => [$group]]])
            ->push(['status' => 601], 200, ['Retry-After' => '90'])
            ->push(['status' => 0, 'body' => ['measuregrps' => []]]);
        $first = $workflow->fetch($snapshot['id'], 'owner', 'withings.weigh_in');
        $this->assertSame(['ok', 'rate_limited'], array_column($first['days'], 'state'));
        $this->assertSame($first, $workflow->fetch($snapshot['id'], 'owner', 'withings.weigh_in'));
        Http::assertSentCount(2); // A normal web fetch never retries or waits.
        $fetcher = $this->fetcher();
        $messages = [];
        $result = iterator_to_array($fetcher->fetch($workflow, $snapshot['id'], 'owner', ['withings.weigh_in'], function ($message) use (&$messages) {
            $messages[] = $message;
        }));
        $this->assertSame(['ok', 'empty'], array_column($result['withings.weigh_in']['days'], 'state'));
        $this->assertSame([30, 30, 30], $fetcher->waits);
        $this->assertStringContainsString('retry 1/3 in 90 seconds', implode('\n', $messages));
        Http::assertSentCount(3);
        $this->assertCount(1, Cache::get('health:task:'.$snapshot['id'].':withings.weigh_in')['entries']);
        $workflow->cancel($snapshot['id'], 'owner');
        try {
            $workflow->fetch($snapshot['id'], 'owner', 'withings.weigh_in', true);
            $this->fail('Cancelled previews cannot be retried');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(410, $e->getStatusCode());
        }
    }

    public function test_shared_cooldown_allows_other_sources_and_caps_retries(): void
    {
        $workflow = $this->mock(PublishingWorkflow::class);
        $workflow->shouldReceive('fetchExpiresAt')->andReturn(now()->timestamp + 1800);
        $fetcher = $this->fetcher();
        $calls = [];
        $workflow->shouldReceive('fetch')->andReturnUsing(function ($id, $owner, $task, $retry) use (&$calls, $fetcher) {
            $calls[] = [$task, $retry, $fetcher->time];

            return ['state' => $task === 'withings.weigh_in' ? 'rate_limited' : 'empty', 'days' => []];
        });
        config(['health.withings_retry_attempts' => 2]);
        $results = iterator_to_array($fetcher->fetch($workflow, 'id', 'owner', ['withings.weigh_in', 'withings.heart', 'oura.sleep', 'apple_health.heart']));
        $this->assertSame([
            ['withings.weigh_in', false, 0.0], ['oura.sleep', false, 0.0], ['apple_health.heart', false, 0.0],
            ['withings.heart', false, 60.0], ['withings.weigh_in', true, 60.0], ['withings.weigh_in', true, 180.0],
        ], $calls);
        $this->assertCount(4, $results);
        $this->assertSame('rate_limited', $results['withings.weigh_in']['state']);
        Http::assertNothingSent();
    }

    public function test_long_retry_after_does_not_expire_preview_or_hammer_remaining_withings_tasks(): void
    {
        $workflow = $this->mock(PublishingWorkflow::class);
        $workflow->shouldReceive('fetchExpiresAt')->andReturn(now()->timestamp + 100);
        $limited = ['state' => 'rate_limited', 'days' => [], 'retry_after' => 300];
        $workflow->shouldReceive('fetch')->with('id', 'owner', 'withings.weigh_in', false)->once()->andReturn($limited);
        $workflow->shouldReceive('fetch')->with('id', 'owner', 'oura.sleep', false)->once()->andReturn(['state' => 'empty', 'days' => []]);
        $workflow->shouldReceive('failedFetch')->withArgs(fn ($id, $owner, $task, $failure) => $task === 'withings.heart' && $failure['state'] === 'rate_limited')->once()->andReturn($limited);
        $fetcher = $this->fetcher();
        $results = iterator_to_array($fetcher->fetch($workflow, 'id', 'owner', ['withings.weigh_in', 'withings.heart', 'oura.sleep']));
        $this->assertCount(3, $results);
        $this->assertSame([], $fetcher->waits);
    }
}
