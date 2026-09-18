<?php

namespace Tests\Feature\Health;

use App\Health\BlogClient;
use App\Health\Collector;
use App\Health\EntryContract;
use App\Health\OuraClient;
use App\Health\WithingsMeasurements;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthJournalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'cache.limiter' => 'array', 'session.driver' => 'array', 'app.url' => 'https://weight.test',
            'health.apple_health_directory' => sys_get_temp_dir().'/absent-health-export-fixture', 'health.oura.client_id' => 'fixture-client', 'health.oura.client_secret' => 'fixture-secret',
            'health.blogs.local.username' => 'local-owner', 'health.blogs.local.password' => 'local-password',
            'health.blogs.production.username' => 'production-owner', 'health.blogs.production.password' => 'production-password']);
        Http::preventStrayRequests();
        $this->withCredentials();
        Cache::flush();
        $this->travelTo(CarbonImmutable::parse('2026-09-16T10:00:00+03:00'));
    }

    private function tokens(): void
    {
        Cache::forever('withings', ['access_token' => 'withings-fixture', 'refresh_token' => 'refresh', 'expires_at' => now()->addHour()->timestamp, 'scopes' => ['user.metrics', 'user.activity']]);
        app(OuraClient::class)->save(['access_token' => 'oura-fixture', 'refresh_token' => 'refresh', 'expires_in' => 3600], config('health.oura.scopes'));
    }

    private function entry(float $weight = 80): array
    {
        return ['schema_version' => 1, 'topic' => 'weight', 'date' => '2026-09-16', 'timezone' => 'Europe/Bucharest', 'providers' => ['withings' => [
            'fetched_at' => '2026-09-16T10:00:00+03:00', 'metrics' => [['key' => 'withings.measure.1', 'label' => 'Weight', 'unit' => 'kg', 'value' => $weight, 'at' => '2026-09-16T08:00:00+03:00']], 'series' => [],
        ]]];
    }

    public function test_web_preview_removes_memory_limit_before_reading_large_exports(): void
    {
        $previous = ini_get('memory_limit');
        $this->mock(\App\Health\AppleHealthExport::class)->shouldReceive('capture')->once()->andReturnUsing(function () {
            $this->assertSame('-1', ini_get('memory_limit'));

            return ['state' => 'empty', 'entries' => []];
        });
        try {
            ini_set('memory_limit', '128M');
            $id = $this->postJson('/health/previews', ['destination' => 'local'])->assertOk()->json('id');
            $this->assertSame('-1', ini_get('memory_limit'));
            $this->withCookie(config('session.cookie'), app('session')->getId());
            $this->deleteJson('/health/previews/'.$id)->assertNoContent();
            Http::assertNothingSent();
        } finally {
            ini_set('memory_limit', $previous);
        }
    }

    public function test_oura_login_uses_exact_callback_and_single_use_state(): void
    {
        $response = $this->get('/login-oura')->assertRedirect();
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('https://weight.test/callback-oura', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame(config('health.oura.scopes'), explode(' ', $query['scope']));
        $this->assertContains('stress', explode(' ', $query['scope']));
        $this->assertContains('heart_health', explode(' ', $query['scope']));
        $this->assertStringNotContainsString('email', $query['scope']);
        Http::fake(['api.ouraring.com/oauth/token' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600])]);
        $this->get('/callback-oura?'.http_build_query(['state' => $query['state'], 'code' => 'c', 'scope' => 'daily']))->assertRedirect('/');
        $this->assertSame(['daily'], app(OuraClient::class)->tokens()['scopes']);
        $this->assertIsString(Cache::get('health:oura:tokens'));
        $this->assertStringNotContainsString('access_token', Cache::get('health:oura:tokens'));
        $this->get('/callback-oura?'.http_build_query(['state' => $query['state'], 'code' => 'c']))->assertStatus(419);
        Http::assertSentCount(1);
    }

    public function test_oura_denial_bad_and_expired_states_do_not_exchange_tokens(): void
    {
        $this->get('/callback-oura?state=wrong&code=x')->assertStatus(419);
        $this->withSession(['health.oura_state' => ['value' => 'state', 'expires_at' => now()->subSecond()->timestamp]])->get('/callback-oura?state=state&code=x')->assertStatus(419);
        $this->withSession(['health.oura_state' => ['value' => 'state', 'expires_at' => now()->addMinute()->timestamp]])->get('/callback-oura?state=state&error=access_denied')->assertRedirect('/');
        Http::assertNothingSent();
    }

    public function test_oura_refresh_rotates_once_and_reuses_new_token(): void
    {
        app(OuraClient::class)->save(['access_token' => 'old', 'refresh_token' => 'old-refresh', 'expires_in' => 1], ['daily']);
        Http::fake(['api.ouraring.com/oauth/token' => Http::response(['access_token' => 'new', 'refresh_token' => 'new-refresh', 'expires_in' => 3600])]);
        $this->assertSame('new', app(OuraClient::class)->accessToken());
        $this->assertSame('new', app(OuraClient::class)->accessToken());
        $this->assertSame('new-refresh', app(OuraClient::class)->tokens()['refresh_token']);
        Http::assertSentCount(1);
    }

    public function test_oura_pagination_and_assigned_sleep_day_are_preserved(): void
    {
        $this->tokens();
        Http::fake(['api.ouraring.com/v2/usercollection/sleep*' => Http::sequence()
            ->push(['data' => [], 'next_token' => null])
            ->push(['data' => [['day' => '2026-09-15', 'bedtime_start' => '2026-09-14T23:00:00+03:00', 'total_sleep_duration' => 24000, 'ring_id' => 'private', 'personal_note' => 'private']], 'next_token' => 'next'])
            ->push(['data' => [['day' => '2026-09-16', 'total_sleep_duration' => 22000]], 'next_token' => null])]);
        $result = app(Collector::class)->fetch('oura.sleep', '2026-09-16', '2026-09-15', now()->toIso8601String());
        $entry = $result['entries']['sleep:2026-09-15'];
        $this->assertCount(1, $entry['providers']['oura']['metrics']);
        $this->assertSame(24000.0, $entry['providers']['oura']['metrics'][0]['value']);
        $this->assertSame('2026-09-14T23:00:00+03:00', $entry['providers']['oura']['metrics'][0]['at']);
        $this->assertStringNotContainsString('private', json_encode($entry));
        $this->assertSame(['2026-09-16', '2026-09-15'], $result['checked_dates']);
        $this->assertSame('ok', $result['days'][1]['state']);
        Http::assertSent(fn ($request) => $request['start_date'] === '2026-09-14' && $request['end_date'] === '2026-09-16');
        Http::assertSentCount(3);
    }

    public function test_missing_permissions_and_errors_are_distinguished(): void
    {
        app(OuraClient::class)->save(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600], ['daily']);
        try {
            app(OuraClient::class)->collection('heartrate', [], 'heartrate');
            $this->fail();
        } catch (\App\Health\ProviderException $e) {
            $this->assertSame('permission', $e->state);
        }
        Http::assertNothingSent();
        Http::fake(['api.ouraring.com/*' => Http::response([], 429)]);
        try {
            app(OuraClient::class)->collection('daily_activity', [], 'daily');
            $this->fail();
        } catch (\App\Health\ProviderException $e) {
            $this->assertSame('rate_limited', $e->state);
        }
    }

    public function test_weight_reader_uses_measurement_time_units_pagination_and_latest_tie(): void
    {
        $this->tokens();
        $t = CarbonImmutable::parse('2026-09-16T08:00:00+03:00')->timestamp;
        $group = fn ($time, $value, $unit) => ['date' => $time, 'created' => $t + 86400, 'category' => 1, 'measures' => [['type' => 1, 'value' => $value, 'unit' => $unit], ['type' => 999, 'value' => 1, 'unit' => -3], ['type' => 6, 'value' => 245, 'unit' => -1]]];
        Http::fake(['wbsapi.withings.net/measure' => Http::sequence()
            ->push(['status' => 0, 'body' => ['measuregrps' => [$group($t, 82000, -3), $group($t - 86400, 1, 0)], 'more' => 1, 'offset' => 2]])
            ->push(['status' => 0, 'body' => ['measuregrps' => [$group($t + 60, 81, 0), $group($t + 120, 810, -1)], 'more' => 0]])]);
        $reader = app(WithingsMeasurements::class);
        $minimum = $reader->minimum($reader->forDay('2026-09-16'));
        $this->assertSame(81.0, $minimum['weight']);
        $this->assertSame($t + 120, $minimum['group']['date']);
        Http::assertSentCount(2);
    }

    public function test_daily_boundaries_follow_bucharest_dst(): void
    {
        $this->tokens();
        $windows = [];
        Http::fake(function ($request) use (&$windows) {
            $windows[] = $request['enddate'] - $request['startdate'] + 1;

            return Http::response(['status' => 0, 'body' => ['measuregrps' => [], 'more' => 0]]);
        });
        app(WithingsMeasurements::class)->forDay('2026-03-29');
        app(WithingsMeasurements::class)->forDay('2026-10-25');
        $this->assertSame([23 * 3600, 25 * 3600], $windows);
    }

    public function test_minimum_body_composition_is_from_the_same_group_only(): void
    {
        $this->tokens();
        $t = now()->timestamp;
        Http::fake(['wbsapi.withings.net/measure' => Http::response(['status' => 0, 'body' => ['measuregrps' => [
            ['date' => $t, 'category' => 1, 'measures' => [['type' => 1, 'value' => 80000, 'unit' => -3], ['type' => 6, 'value' => 250, 'unit' => -1], ['type' => 11, 'value' => 70, 'unit' => 0]]],
            ['date' => $t - 60, 'category' => 1, 'measures' => [['type' => 1, 'value' => 79000, 'unit' => -3], ['type' => 6, 'value' => 240, 'unit' => -1]]],
        ]]])]);
        $result = app(Collector::class)->fetch('withings.weigh_in', '2026-09-16', '2026-09-15', now()->toIso8601String());
        $this->assertSame(24.0, $result['entries']['body-composition:2026-09-16']['providers']['withings']['metrics'][0]['value']);
        $this->assertArrayNotHasKey('heart:2026-09-16', $result['entries']);
    }

    public function test_existing_import_response_shape_remains_compatible(): void
    {
        $this->tokens();
        Http::fake(['wbsapi.withings.net/measure' => Http::response(['status' => 0, 'body' => ['measuregrps' => [['date' => now()->timestamp, 'category' => 1, 'measures' => [['type' => 1, 'value' => 81234, 'unit' => -3]]]]]])]);
        $this->postJson('/weight/get-from-withings', ['date' => '2026-09-16'])->assertOk()->assertExactJson(['weight' => '81.23']);
    }

    public function test_contract_strips_untrusted_labels_and_rejects_unknown_fields(): void
    {
        $entry = $this->entry();
        $entry['providers']['withings']['metrics'][0]['label'] = '<script>private</script>';
        $this->assertSame('Weight', EntryContract::normalize($entry)['providers']['withings']['metrics'][0]['label']);
        $entry['providers']['withings']['account_id'] = 'private';
        $this->expectException(\InvalidArgumentException::class);
        EntryContract::normalize($entry);
    }

    public function test_contract_rejects_unknown_types_and_wrong_topic(): void
    {
        $entry = $this->entry();
        $entry['topic'] = 'heart';
        $this->expectException(\InvalidArgumentException::class);
        EntryContract::normalize($entry);
    }

    public function test_merge_preserves_metrics_missing_from_a_partial_refresh(): void
    {
        $old = $this->entry();
        $old['topic'] = 'heart';
        $old['providers']['withings']['metrics'][0] = ['key' => 'withings.measure.11', 'label' => 'Heart rate', 'value' => 70, 'unit' => 'bpm', 'at' => '2026-09-15T08:00:00+03:00'];
        $incoming = $old;
        unset($incoming['providers']['withings']);
        $incoming['providers']['oura'] = ['fetched_at' => now()->toIso8601String(), 'metrics' => [['key' => 'oura.sleep.average_heart_rate', 'value' => 60, 'at' => '2026-09-15T08:00:00+03:00']], 'series' => []];
        $this->assertCount(2, EntryContract::merge($old, $incoming)['providers']);
    }

    public function test_snapshots_are_session_bound_cancellable_and_expiring(): void
    {
        $id = $this->postJson('/health/previews', ['destination' => 'local'])->assertOk()->assertJsonPath('fetch_concurrency', 4)->json('id');
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $this->deleteJson('/health/previews/'.$id)->assertNoContent();
        $this->postJson('/health/previews/'.$id.'/prepare')->assertStatus(410);
        $id = $this->postJson('/health/previews', ['destination' => 'local'])->json('id');
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $this->travel(31)->minutes();
        $this->postJson('/health/previews/'.$id.'/prepare')->assertStatus(410);
        Http::assertNothingSent();
    }

    public function test_preview_and_publish_are_separate_and_destination_bound(): void
    {
        $entry = $this->entry();
        $this->mock(Collector::class, function ($mock) use ($entry) {
            $mock->shouldReceive('fetch')->andReturnUsing(fn ($task) => ['state' => $task === 'withings.weigh_in' ? 'ok' : 'empty', 'message' => 'Fixture', 'entries' => $task === 'withings.weigh_in' ? ['weight:2026-09-16' => $entry] : []]);
        });
        Http::fake(function ($request) {
            if ($request->method() === 'GET') {
                return Http::response(['schema_version' => 1, 'entry' => null, 'revision' => null]);
            }

            return Http::response(['schema_version' => 1, 'operation' => 'created', 'id' => 123, 'url' => 'https://blog.test/health/weight-2026-09-16/', 'revision' => str_repeat('a', 64)]);
        });
        $id = $this->postJson('/health/previews', ['destination' => 'local'])->json('id');
        $this->withCookie(config('session.cookie'), app('session')->getId());
        foreach (Collector::tasks() as $task) {
            $this->postJson('/health/previews/'.$id.'/fetch/'.$task)->assertOk();
        }
        $this->postJson('/health/previews/'.$id.'/prepare')->assertOk()->assertJsonPath('entries.weight:2026-09-16.operation', 'create');
        Http::assertSentCount(1);
        $this->postJson('/health/previews/'.$id.'/publish', ['destination' => 'production', 'entry' => 'weight:2026-09-16'])->assertStatus(409);
        Http::assertSentCount(1);
        $this->postJson('/health/previews/'.$id.'/publish', ['destination' => 'local', 'entry' => 'weight:2026-09-16'])->assertOk();
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->hasHeader('Authorization', 'Basic '.base64_encode('local-owner:local-password')) && $request['providers']['withings']['metrics'][0]['value'] === 80.0);
        Http::assertSentCount(2);
    }

    public function test_blog_credentials_never_cross_destinations_and_stale_is_reported(): void
    {
        Http::fake(['blog.test/*' => Http::response(['schema_version' => 1, 'entry' => null]), 'pacurar.dev/*' => Http::response([], 409)]);
        app(BlogClient::class)->read('local', 'weight', '2026-09-16');
        try {
            app(BlogClient::class)->publish('production', $this->entry(), null);
            $this->fail();
        } catch (\App\Health\ProviderException $e) {
            $this->assertSame('stale', $e->state);
        }
        Http::assertSent(fn ($r) => str_contains($r->url(), 'blog.test') && $r->hasHeader('Authorization', 'Basic '.base64_encode('local-owner:local-password')));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'pacurar.dev') && $r->hasHeader('Authorization', 'Basic '.base64_encode('production-owner:production-password')));
    }

    public function test_php_oversized_body_warning_is_reported_without_exposing_server_output(): void
    {
        Http::fake(['blog.test/*' => Http::response('<br>Warning: PHP Request Startup: POST Content-Length of 3207312 bytes exceeds the limit of 2097152 bytes in Unknown on line 0<br>private-server-path', 200, ['Content-Type' => 'text/html'])]);
        try {
            app(BlogClient::class)->publish('local', $this->entry(), null);
            $this->fail('Oversized request should fail.');
        } catch (\App\Health\ProviderException $e) {
            $this->assertSame('configuration', $e->state);
            $this->assertStringContainsString('post_max_size', $e->getMessage());
            $this->assertStringNotContainsString('private-server-path', $e->getMessage());
        }
    }

    public function test_blog_endpoint_size_limit_has_a_specific_error(): void
    {
        Http::fake(['blog.test/*' => Http::response(['code' => 'health_large'], 413)]);
        $this->expectException(\App\Health\ProviderException::class);
        $this->expectExceptionMessage('blog endpoint’s 4 MB limit');
        app(BlogClient::class)->publish('local', $this->entry(), null);
    }

    public function test_web_server_size_limit_has_a_specific_error(): void
    {
        Http::fake(['blog.test/*' => Http::response('<h1>Request Entity Too Large</h1>', 413)]);
        $this->expectException(\App\Health\ProviderException::class);
        $this->expectExceptionMessage('request size limit is too low');
        app(BlogClient::class)->publish('local', $this->entry(), null);
    }

    public function test_destination_is_remembered_in_cache_and_only_known_blogs_are_accepted(): void
    {
        $this->getJson('/health/status')->assertOk()->assertJsonPath('destination', 'local');
        $this->postJson('/health/destination', ['destination' => 'production'])->assertNoContent();
        $this->assertSame('production', Cache::get('health:last_destination'));
        $this->getJson('/health/status')->assertOk()->assertJsonPath('destination', 'production');
        $this->postJson('/health/destination', ['destination' => 'https://other.test'])->assertUnprocessable();
        $this->getJson('/health/status')->assertOk()->assertJsonPath('destination', 'production');
        $this->postJson('/health/destination', ['destination' => 'local'])->assertNoContent();
        $this->getJson('/health/status')->assertOk()->assertJsonPath('destination', 'local');
        Cache::forget('health:last_destination');
        $this->getJson('/health/status')->assertOk()->assertJsonPath('destination', 'local');
        Http::assertNothingSent();
    }

    public function test_status_never_exposes_credentials(): void
    {
        $this->tokens();
        $response = $this->getJson('/health/status')->assertOk();
        foreach (['local-password', 'production-password', 'fixture-secret', 'oura-fixture', 'withings-fixture', 'refresh_token'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
    }

    public function test_oura_accepts_scopes_from_token_response_or_oauth_default(): void
    {
        Http::fake(['api.ouraring.com/oauth/token' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600, 'scope' => 'daily heartrate'])]);
        $this->withSession(['health.oura_state' => ['value' => 'first', 'expires_at' => now()->addMinute()->timestamp, 'scopes' => ['daily', 'heartrate', 'workout']]])
            ->get('/callback-oura?state=first&code=c')->assertRedirect('/');
        $this->assertSame(['daily', 'heartrate'], app(OuraClient::class)->tokens()['scopes']);
        Http::fake(['api.ouraring.com/oauth/token' => Http::response(['access_token' => 'b', 'refresh_token' => 's', 'expires_in' => 3600])]);
        $this->withSession(['health.oura_state' => ['value' => 'second', 'expires_at' => now()->addMinute()->timestamp, 'scopes' => ['daily', 'heartrate']]])
            ->get('/callback-oura?state=second&code=c')->assertRedirect('/');
        $this->assertSame(['daily', 'heartrate'], app(OuraClient::class)->tokens()['scopes']);
    }

    public function test_blog_connection_check_is_read_only_and_uses_selected_credentials(): void
    {
        Http::fake(['pacurar.dev/wp-json/pacurar2020/v1/health-connection' => Http::response(['schema_version' => 1, 'can_publish' => true, 'username' => 'publisher'])]);
        $this->postJson('/health/test-connection', ['destination' => 'production'])->assertOk()->assertJsonPath('connected', true);
        Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->hasHeader('Authorization', 'Basic '.base64_encode('production-owner:production-password')));
        Http::assertSentCount(1);
    }

    public function test_prefixed_oura_permissions_work_for_existing_tokens_and_refresh(): void
    {
        Cache::forever('health:oura:tokens', Crypt::encryptString(json_encode([
            'access_token' => 'old', 'refresh_token' => 'old-refresh',
            'expires_at' => now()->timestamp, 'scopes' => ['extapi:daily', 'extapi:heartrate'],
        ])));
        $this->getJson('/health/status')->assertOk()->assertJsonPath('providers.oura.missing_scopes', ['workout', 'session', 'spo2', 'stress', 'heart_health']);
        Http::fake([
            'api.ouraring.com/oauth/token' => Http::response(['access_token' => 'new', 'refresh_token' => 'rotated', 'expires_in' => 3600, 'scope' => 'extapi:daily extapi:heartrate']),
            'api.ouraring.com/v2/usercollection/daily_activity*' => Http::response(['data' => [], 'next_token' => null]),
        ]);
        $this->assertSame([], app(OuraClient::class)->collection('daily_activity', [], 'daily'));
        $this->assertSame(['daily', 'heartrate'], app(OuraClient::class)->tokens()['scopes']);
        Http::assertSentCount(2);
    }

    public function test_oura_callback_normalizes_prefixed_consent_without_adding_ungranted_scopes(): void
    {
        Http::fake(['api.ouraring.com/oauth/token' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600])]);
        $this->withSession(['health.oura_state' => ['value' => 'prefixed', 'expires_at' => now()->addMinute()->timestamp, 'scopes' => config('health.oura.scopes')]])
            ->get('/callback-oura?'.http_build_query(['state' => 'prefixed', 'code' => 'c', 'scope' => 'extapi:daily extapi:spo2']))->assertRedirect('/');
        $this->assertSame(['daily', 'spo2'], app(OuraClient::class)->tokens()['scopes']);
        $this->assertSame('stress', Collector::OURA['daily_stress']);
        $this->assertSame('stress', Collector::OURA['daily_resilience']);
        $this->assertSame('heart_health', Collector::OURA['daily_cardiovascular_age']);
        $this->assertSame('heart_health', Collector::OURA['vO2_max']);
    }

    public function test_each_source_reports_its_frozen_requested_date_even_after_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16T23:59:00+03:00'));
        $start = $this->postJson('/health/previews', ['destination' => 'local'])->assertOk()->assertJsonPath('timezone', 'Europe/Bucharest');
        foreach (Collector::tasks() as $task) {
            $this->assertSame(['2026-09-16', '2026-09-15'], $start->json('task_dates')[$task]);
        }
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $this->travel(2)->minutes();
        $this->postJson('/health/previews/'.$start->json('id').'/fetch/oura.daily_activity')->assertOk()
            ->assertJsonPath('days.0.date', '2026-09-16')->assertJsonPath('days.1.date', '2026-09-15')->assertJsonPath('checked_dates', ['2026-09-16', '2026-09-15'])->assertJsonPath('state', 'reconnect');
        Http::assertNothingSent();
    }

    public function test_today_zero_and_yesterdays_measurements_are_both_returned(): void
    {
        $this->tokens();
        Http::fake(['api.ouraring.com/v2/usercollection/daily_activity*' => Http::response([
            'data' => [['day' => '2026-09-16', 'steps' => 0], ['day' => '2026-09-15', 'steps' => 500]], 'next_token' => null,
        ])]);
        $result = app(Collector::class)->fetch('oura.daily_activity', '2026-09-16', '2026-09-15', now()->toIso8601String());
        $this->assertSame(['2026-09-16', '2026-09-15'], $result['checked_dates']);
        $this->assertSame('ok', $result['days'][0]['state']);
        $this->assertSame(0.0, $result['entries']['activity:2026-09-16']['providers']['oura']['metrics'][0]['value']);
        Http::assertSent(fn ($r) => $r['start_date'] === '2026-09-16');
        $this->assertSame(500.0, $result['entries']['activity:2026-09-15']['providers']['oura']['metrics'][0]['value']);
        Http::assertSentCount(2);
    }

    public function test_empty_today_does_not_hide_yesterday_or_relabel_its_entry(): void
    {
        $this->tokens();
        Http::fake(['api.ouraring.com/v2/usercollection/daily_activity*' => Http::sequence()
            ->push(['data' => [['day' => '2026-09-16', 'steps' => null]], 'next_token' => null])
            ->push(['data' => [['day' => '2026-09-15', 'steps' => 1200]], 'next_token' => null])]);
        $result = app(Collector::class)->fetch('oura.daily_activity', '2026-09-16', '2026-09-15', now()->toIso8601String());
        $this->assertSame('2026-09-15', $result['days'][1]['date']);
        $this->assertSame('ok', $result['days'][1]['state']);
        $this->assertSame(['activity:2026-09-15'], array_keys($result['entries']));
        Http::assertSentCount(2);
    }

    public function test_missing_both_days_stays_empty_and_errors_still_check_both_days(): void
    {
        $this->tokens();
        Http::fake(['api.ouraring.com/v2/usercollection/daily_activity*' => Http::sequence()
            ->push(['data' => [], 'next_token' => null])->push(['data' => [], 'next_token' => null])->push([], 429)->push([], 429)]);
        $result = app(Collector::class)->fetch('oura.daily_activity', '2026-09-16', '2026-09-15', now()->toIso8601String());
        $this->assertSame('empty', $result['state']);
        $this->assertSame([], $result['entries']);
        $this->assertSame(['2026-09-16', '2026-09-15'], $result['checked_dates']);
        Http::assertSentCount(2);
        $result = app(Collector::class)->fetch('oura.daily_activity', '2026-09-16', '2026-09-15', now()->toIso8601String());
        $this->assertSame('rate_limited', $result['state']);
        $this->assertSame(['2026-09-16', '2026-09-15'], $result['checked_dates']);
        Http::assertSentCount(4);
    }

    public function test_yesterdays_weight_keeps_composition_with_its_minimum_group(): void
    {
        $this->tokens();
        $yesterday = CarbonImmutable::parse('2026-09-15T08:00:00+03:00')->timestamp;
        Http::fake(['wbsapi.withings.net/measure' => Http::sequence()
            ->push(['status' => 0, 'body' => ['measuregrps' => []]])
            ->push(['status' => 0, 'body' => ['measuregrps' => [
                ['date' => $yesterday, 'category' => 1, 'measures' => [['type' => 1, 'value' => 80, 'unit' => 0], ['type' => 6, 'value' => 25, 'unit' => 0]]],
                ['date' => $yesterday + 60, 'category' => 1, 'measures' => [['type' => 1, 'value' => 79, 'unit' => 0], ['type' => 6, 'value' => 24, 'unit' => 0]]],
            ]]])]);
        $result = app(Collector::class)->fetch('withings.weigh_in', '2026-09-16', '2026-09-15', now()->toIso8601String());
        $this->assertSame(['weight:2026-09-15', 'body-composition:2026-09-15'], array_keys($result['entries']));
        $this->assertSame(24.0, $result['entries']['body-composition:2026-09-15']['providers']['withings']['metrics'][0]['value']);
        $this->assertSame('ok', $result['days'][1]['state']);
        Http::assertSentCount(2);
    }

    public function test_preview_reports_each_day_and_freezes_both_across_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-16T23:59:00+03:00'));
        $this->tokens();
        $start = $this->postJson('/health/previews', ['destination' => 'local'])->assertOk();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $this->travel(2)->minutes();
        Http::fake(['api.ouraring.com/v2/usercollection/daily_activity*' => Http::sequence()
            ->push(['data' => []])->push(['data' => [['day' => '2026-09-15', 'steps' => 100]]])]);
        $this->postJson('/health/previews/'.$start->json('id').'/fetch/oura.daily_activity')->assertOk()
            ->assertJsonPath('days.0.date', '2026-09-16')->assertJsonPath('days.0.state', 'empty')->assertJsonPath('days.1.date', '2026-09-15')->assertJsonPath('days.1.state', 'ok')->assertJsonPath('checked_dates', ['2026-09-16', '2026-09-15']);
        Http::assertSentCount(2);
    }

    public function test_ongoing_rest_period_stops_at_fetch_time_instead_of_future_midnight(): void
    {
        $this->tokens();
        Http::fake(['api.ouraring.com/v2/usercollection/rest_mode_period*' => Http::response([
            'data' => [['start_day' => '2026-09-15', 'start_time' => '2026-09-15T18:00:00+03:00', 'end_day' => null, 'end_time' => null]],
        ])]);
        $result = app(Collector::class)->fetch('oura.rest_mode_period', '2026-09-16', '2026-09-15', now()->toIso8601String());
        $this->assertSame(36000.0, $result['entries']['recovery:2026-09-16']['providers']['oura']['metrics'][0]['value']);
        $this->assertSame('ok', $result['days'][0]['state']);
        $this->assertSame(21600.0, $result['entries']['recovery:2026-09-15']['providers']['oura']['metrics'][0]['value']);
        Http::assertSentCount(2);
    }

    public function test_one_day_failing_does_not_discard_the_other_days_data(): void
    {
        $this->tokens();
        Http::fake(['api.ouraring.com/v2/usercollection/daily_activity*' => Http::sequence()
            ->push([], 500)->push(['data' => [['day' => '2026-09-15', 'steps' => 500]]])
            ->push(['data' => [['day' => '2026-09-16', 'steps' => 100]]])->push([], 500)]);
        foreach ([['activity:2026-09-15', 1], ['activity:2026-09-16', 0]] as [$key, $successfulDay]) {
            $result = app(Collector::class)->fetch('oura.daily_activity', '2026-09-16', '2026-09-15', now()->toIso8601String());
            $this->assertSame('partial', $result['state']);
            $this->assertSame([$key], array_keys($result['entries']));
            $this->assertSame('ok', $result['days'][$successfulDay]['state']);
            $this->assertNotSame('ok', $result['days'][1 - $successfulDay]['state']);
        }
        Http::assertSentCount(4);
    }

    public function test_both_days_use_their_own_lowest_weight_and_matching_composition(): void
    {
        $this->tokens();
        $group = fn ($date, $weight, $fat) => ['date' => CarbonImmutable::parse($date)->timestamp, 'category' => 1, 'measures' => [['type' => 1, 'value' => $weight, 'unit' => 0], ['type' => 6, 'value' => $fat, 'unit' => 0]]];
        Http::fake(['wbsapi.withings.net/measure' => Http::response(['status' => 0, 'body' => ['measuregrps' => [
            $group('2026-09-16T08:00:00+03:00', 81, 22), $group('2026-09-16T09:00:00+03:00', 80, 21),
            $group('2026-09-15T08:00:00+03:00', 82, 24), $group('2026-09-15T09:00:00+03:00', 81, 23),
        ]]])]);
        $result = app(Collector::class)->fetch('withings.weigh_in', '2026-09-16', '2026-09-15', now()->toIso8601String());
        $this->assertSame('ok', $result['state']);
        $this->assertCount(4, $result['entries']);
        foreach (['2026-09-16' => [80, 21], '2026-09-15' => [81, 23]] as $date => [$weight, $fat]) {
            $this->assertSame((float) $weight, $result['entries']['weight:'.$date]['providers']['withings']['metrics'][0]['value']);
            $this->assertSame((float) $fat, $result['entries']['body-composition:'.$date]['providers']['withings']['metrics'][0]['value']);
        }
        Http::assertSentCount(2);
    }

    public function test_later_run_updates_yesterday_creates_today_and_retries_without_duplicates(): void
    {
        $makeEntry = fn ($date, $steps) => ['schema_version' => 1, 'topic' => 'activity', 'date' => $date, 'timezone' => 'Europe/Bucharest', 'providers' => ['oura' => [
            'fetched_at' => now()->toIso8601String(), 'metrics' => [['key' => 'oura.daily_activity.steps', 'value' => $steps, 'at' => $date.'T00:00:00+03:00']], 'series' => [],
        ]]];
        $old = $makeEntry('2026-09-15', 100);
        $old['providers']['withings'] = ['fetched_at' => now()->toIso8601String(), 'metrics' => [['key' => 'withings.activity.steps', 'value' => 80, 'at' => '2026-09-15T00:00:00+03:00']], 'series' => []];
        $posts = ['2026-09-15' => ['id' => 7, 'entry' => EntryContract::normalize($old), 'revision' => str_repeat('a', 64)]];
        $phase = 0;
        $this->mock(Collector::class, function ($mock) use (&$phase, $makeEntry) {
            $mock->shouldReceive('fetch')->andReturnUsing(function ($task) use (&$phase, $makeEntry) {
                $entries = [];
                if ($task === 'oura.daily_activity') {
                    $entries['activity:2026-09-15'] = $makeEntry('2026-09-15', $phase === 0 ? 200 : 250);
                    if ($phase > 0) {
                        $entries['activity:2026-09-16'] = $makeEntry('2026-09-16', 50);
                    }
                }

                return ['state' => $entries ? 'ok' : 'empty', 'message' => 'Fixture', 'entries' => $entries];
            });
        });
        Http::fake(function ($request) use (&$posts) {
            $date = $request['date'];
            $old = $posts[$date] ?? null;
            if ($request->method() === 'GET') {
                return Http::response(['schema_version' => 1, 'entry' => $old['entry'] ?? null, 'revision' => $old['revision'] ?? null]);
            }
            $entry = EntryContract::normalize($request->data());
            $operation = ! $old ? 'created' : (EntryContract::fingerprint($old['entry']) === EntryContract::fingerprint($entry) ? 'unchanged' : 'updated');
            if ($operation !== 'unchanged' && $request['expected_revision'] !== ($old['revision'] ?? null)) {
                return Http::response([], 409);
            }
            $posts[$date] = ['id' => $old['id'] ?? 8, 'entry' => $entry, 'revision' => EntryContract::fingerprint($entry)];

            return Http::response(['schema_version' => 1, 'id' => $posts[$date]['id'], 'operation' => $operation, 'revision' => $posts[$date]['revision'], 'url' => 'https://blog.test/health/activity-'.$date.'/']);
        });
        for ($phase = 0; $phase < 3; $phase++) {
            $id = $this->postJson('/health/previews', ['destination' => 'local'])->assertOk()->json('id');
            $this->withCookie(config('session.cookie'), app('session')->getId());
            foreach (Collector::tasks() as $task) {
                $this->postJson('/health/previews/'.$id.'/fetch/'.$task)->assertOk();
            }
            $writes = Http::recorded(fn ($request) => $request->method() === 'POST')->count();
            $preview = $this->postJson('/health/previews/'.$id.'/prepare')->assertOk();
            $this->assertSame($writes, Http::recorded(fn ($request) => $request->method() === 'POST')->count());
            $preview->assertJsonPath('entries.activity:2026-09-15.operation', $phase === 2 ? 'unchanged' : 'update');
            $preview->assertJsonPath('entries.activity:2026-09-15.entry.providers.withings.metrics.0.value', 80);
            if ($phase > 0) {
                $preview->assertJsonPath('entries.activity:2026-09-16.operation', $phase === 2 ? 'unchanged' : 'create');
                $this->assertSame(['activity:2026-09-16', 'activity:2026-09-15'], array_keys($preview->json('entries')));
            }
            foreach ($preview->json('entries') as $key => $item) {
                $this->postJson('/health/previews/'.$id.'/publish', ['destination' => 'local', 'entry' => $key])->assertOk();
                $this->postJson('/health/previews/'.$id.'/publish', ['destination' => 'local', 'entry' => $key])->assertOk()->assertJsonPath('operation', 'unchanged');
            }
        }
        $this->assertCount(2, $posts);
        $this->assertSame(7, $posts['2026-09-15']['id']);
        $this->assertSame(8, $posts['2026-09-16']['id']);
        $this->assertSame(250.0, $posts['2026-09-15']['entry']['providers']['oura']['metrics'][0]['value']);
        $this->assertSame(50.0, $posts['2026-09-16']['entry']['providers']['oura']['metrics'][0]['value']);
    }
}
