<?php

namespace Tests\Feature\Health;

use App\Health\AppleHealthExport;
use App\Health\Collector;
use App\Health\EntryContract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppleHealthExportTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/health-export-test-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700);
        config(['health.apple_health_directory' => $this->directory, 'cache.default' => 'array', 'cache.limiter' => 'array', 'session.driver' => 'array', 'health.blogs.local.username' => 'fixture', 'health.blogs.local.password' => 'fixture']);
        Http::preventStrayRequests();
        Cache::flush();
        $this->withCredentials();
        $this->travelTo(CarbonImmutable::parse('2026-09-16T18:00:00+03:00'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function export(string $folder, array $data, int $mtime = 100): string
    {
        $path = $this->directory.'/'.$folder;
        if (! is_dir($path)) {
            mkdir($path, 0700);
        }
        file_put_contents($path.'/export.json', json_encode(['data' => $data]));
        touch($path, $mtime);
        clearstatcache();

        return $path;
    }

    private function metric(string $name = 'step_count', float $value = 123, string $at = '2026-09-16 12:00:00 AM +0300', string $unit = 'count'): array
    {
        return ['name' => $name, 'units' => $unit, 'data' => [['date' => $at, 'qty' => $value, 'source' => 'private device']]];
    }

    private function workout(string $source = 'Apple Watch'): array
    {
        return ['id' => 'private-id', 'name' => 'Outdoor Run', 'source' => ['name' => $source, 'identifier' => 'private-device'], 'start' => '2026-09-15 7:45:09 PM +0300', 'end' => '2026-09-15 7:52:09 PM +0300', 'duration' => 420,
            'activeEnergyBurned' => ['qty' => 292.88, 'units' => 'kJ'], 'distance' => ['qty' => 1010, 'units' => 'm'], 'avgHeartRate' => ['qty' => 141, 'units' => 'count/min'],
            'heartRateData' => [['date' => '2026-09-15 7:46:00 PM +0300', 'Avg' => 142, 'Min' => 140, 'Max' => 145, 'units' => 'count/min']],
            'splits' => [['items' => [['index' => 1, 'start' => '2026-09-15 7:45:09 PM +0300', 'metrics' => ['distance' => ['qty' => 1000, 'units' => 'm']]]]]],
            'route' => [['lat' => 44.1, 'lon' => 20]], 'metadata' => ['note' => 'private note']];
    }

    private function capture(): array
    {
        return app(AppleHealthExport::class)->capture(now()->toIso8601String());
    }

    public function test_only_newest_folder_is_used_and_empty_newest_does_not_fall_back(): void
    {
        $this->export('z-old', ['metrics' => [$this->metric(value: 100)]], 100);
        $this->export('a-new', ['metrics' => [$this->metric(value: 0)]], 200);
        $snapshot = $this->capture();
        $this->assertSame('a-new', $snapshot['folder']);
        $this->assertSame(0.0, $snapshot['entries']['activity']['2026-09-16']['providers']['apple_health']['metrics'][0]['value']);
        mkdir($this->directory.'/new-empty');
        touch($this->directory.'/new-empty', 300);
        clearstatcache();
        $snapshot = $this->capture();
        $this->assertSame('new-empty', $snapshot['folder']);
        $this->assertSame('error', $snapshot['state']);
        $this->assertSame([], $snapshot['entries']);
    }

    public function test_units_unicode_dates_sleep_day_and_private_fields(): void
    {
        $this->export('current', ['metrics' => [$this->metric('active_energy', 418.4, '2026-09-16 12:00:00 AM +0300', 'kJ'),
            ['name' => 'sleep_analysis', 'units' => 'hr', 'data' => [['date' => '2026-09-16 12:00:00 AM +0300', 'totalSleep' => 8, 'sleepStart' => '2026-09-15 11:00:00 PM +0300', 'sleepEnd' => '2026-09-16 7:00:00 AM +0300']]]], 'workouts' => [$this->workout()]]);
        $s = $this->capture();
        $this->assertSame('ok', $s['state']);
        $this->assertEqualsWithDelta(100, $s['entries']['activity']['2026-09-16']['providers']['apple_health']['metrics'][0]['value'], .0001);
        $this->assertArrayHasKey('2026-09-16', $s['entries']['sleep']);
        $w = $s['entries']['workouts']['2026-09-15']['providers']['apple_health']['workouts'][0];
        $this->assertSame('outdoor-run', $w['type']);
        $this->assertSame('2026-09-15T19:45:09+03:00', $w['start']);
        $metrics = array_column($w['metrics'], 'value', 'key');
        $this->assertEqualsWithDelta(70, $metrics['apple_health.workout.activeEnergyBurned'], .0001);
        $this->assertSame(1.01, $metrics['apple_health.workout.distance']);
        $this->assertSame(1.0, $metrics['apple_health.workout.splits.metrics.distance']);
        $this->assertCount(3, $w['series']);
        foreach (['private', 'identifier', 'metadata', 'route', 'latitude', 'source'] as $text) {
            $this->assertStringNotContainsString($text, json_encode($s['entries']));
        }
    }

    public function test_duplicate_files_are_not_added_and_latest_observation_wins(): void
    {
        $path = $this->export('current', ['metrics' => [$this->metric()], 'workouts' => [$this->workout()]]);
        touch($path.'/export.json', 100);
        file_put_contents($path.'/new.json', json_encode(['data' => ['metrics' => [$this->metric(value: 456)], 'workouts' => [$this->workout()]]]));
        touch($path.'/new.json', 200);
        $s = $this->capture();
        $this->assertSame(1, $s['workouts']);
        $this->assertSame(456.0, $s['entries']['activity']['2026-09-16']['providers']['apple_health']['metrics'][0]['value']);
    }

    public function test_today_and_yesterday_exported_data_keep_actual_dates(): void
    {
        $this->export('current', ['metrics' => [$this->metric(value: 0), $this->metric(value: 600, at: '2026-09-15 12:00:00 AM +0300')], 'workouts' => [$this->workout()]]);
        $s = $this->capture();
        $collector = app(Collector::class);
        $today = $collector->fetch('apple_health.activity', '2026-09-16', '2026-09-15', now()->toIso8601String(), $s);
        $this->assertCount(2, $today['entries']);
        $this->assertSame('ok', $today['days'][1]['state']);
        $this->assertSame(['2026-09-16', '2026-09-15'], $today['checked_dates']);
        $yesterday = $collector->fetch('apple_health.workouts', '2026-09-16', '2026-09-15', now()->toIso8601String(), $s);
        $this->assertSame('empty', $yesterday['days'][0]['state']);
        $this->assertSame('ok', $yesterday['days'][1]['state']);
        $this->assertArrayHasKey('activity:2026-09-15', $yesterday['entries']);
    }

    public function test_dst_offsets_and_ecg_subsecond_samples_are_preserved(): void
    {
        $this->export('current', ['metrics' => [['name' => 'heart_rate', 'units' => 'count/min', 'data' => [
            ['date' => '2026-10-25 03:30:00 +0300', 'Avg' => 60], ['date' => '2026-10-25 03:30:00 +0200', 'Avg' => 62]]]],
            'ecg' => [['start' => '2026-09-16T10:00:00+03:00', 'end' => '2026-09-16T10:00:30+03:00', 'averageHeartRate' => 70, 'classification' => 'private text', 'voltageMeasurements' => [
                ['date' => 1789542000.002, 'voltage' => 12, 'units' => 'mcV'], ['date' => 1789542000.001, 'voltage' => 11, 'units' => 'mcV']]]]]);
        $s = $this->capture();
        $this->assertSame('ok', $s['state']);
        $metrics = $s['entries']['heart']['2026-10-25']['providers']['apple_health']['metrics'];
        $this->assertCount(2, $metrics);
        $this->assertNotSame($metrics[0]['at'], $metrics[1]['at']);
        $points = $s['entries']['ecg']['2026-09-16']['providers']['apple_health']['series'][0]['points'];
        $this->assertCount(2, $points);
        $this->assertSame(11.0, $points[0]['value']);
        $this->assertStringNotContainsString('classification', json_encode($s['entries']));
    }

    public function test_mindful_minutes_are_available_for_both_days_with_public_units(): void
    {
        $this->export('current', ['metrics' => [
            $this->metric('mindful_minutes', 12.5, unit: 'min'),
            $this->metric('mindful_minutes', 0.5, at: '2026-09-15 12:00:00 AM +0300', unit: 'hr'),
        ]]);
        $snapshot = $this->capture();
        $this->assertSame('ok', $snapshot['state']);
        $this->assertEmpty($snapshot['warnings']);
        $this->assertContains('apple_health.mindfulness', Collector::tasks());
        $result = app(Collector::class)->fetch('apple_health.mindfulness', '2026-09-16', '2026-09-15', now()->toIso8601String(), $snapshot);
        foreach (['2026-09-16' => 12.5, '2026-09-15' => 30.0] as $date => $minutes) {
            $entry = $result['entries']['mindfulness:'.$date];
            $metric = $entry['providers']['apple_health']['metrics'][0];
            $this->assertSame('Mindful minutes', $metric['label']);
            $this->assertSame('min', $metric['unit']);
            $this->assertSame($minutes, $metric['value']);
            $this->assertStringStartsWith($date, $metric['at']);
            $this->assertStringNotContainsString('private device', json_encode($entry));
        }
    }

    public function test_invalid_newest_json_and_unknown_units_are_reported(): void
    {
        $path = $this->export('current', ['metrics' => [$this->metric(unit: 'mystery')]]);
        $s = $this->capture();
        $this->assertNotEmpty($s['warnings']);
        $this->assertSame([], $s['entries']);
        file_put_contents($path.'/export.json', '{');
        $s = $this->capture();
        $this->assertSame('error', $s['state']);
        $result = app(Collector::class)->fetch('apple_health.activity', '2026-09-16', '2026-09-15', now()->toIso8601String(), $s);
        $this->assertSame(['2026-09-16', '2026-09-15'], $result['checked_dates']);
    }

    public function test_cycling_distance_and_waist_circumference_keep_dates_topics_and_convert_units(): void
    {
        $this->export('current', ['metrics' => [
            $this->metric('cycling_distance', 12.5, unit: 'km'),
            $this->metric('cycling_distance', 2500, at: '2026-09-15 12:00:00 AM +0300', unit: 'm'),
            $this->metric('cycling_distance', 2, at: '2026-08-18 12:00:00 AM +0300', unit: 'mi'),
            $this->metric('waist_circumference', 82, unit: 'cm'),
            $this->metric('waist_circumference', .83, at: '2026-09-15 12:00:00 AM +0300', unit: 'm'),
            $this->metric('waist_circumference', 32, at: '2026-08-18 12:00:00 AM +0300', unit: 'in'),
        ]]);
        $snapshot = $this->capture();
        $this->assertSame('ok', $snapshot['state']);
        $this->assertEmpty($snapshot['warnings']);
        foreach ([
            ['activity', 'cycling_distance', 'Cycling distance', 'km', ['2026-09-16' => 12.5, '2026-09-15' => 2.5, '2026-08-18' => 3.218688]],
            ['body-composition', 'waist_circumference', 'Waist circumference', 'cm', ['2026-09-16' => 82, '2026-09-15' => 83, '2026-08-18' => 81.28]],
        ] as [$topic, $name, $label, $unit, $dates]) {
            foreach ($dates as $date => $expected) {
                $result = app(AppleHealthExport::class)->fetchDay($snapshot, $topic, $date);
                $entry = EntryContract::normalize($result['entries'][$topic.':'.$date]);
                $metric = $entry['providers']['apple_health']['metrics'][0];
                $this->assertSame('apple_health.'.$name, $metric['key']);
                $this->assertSame($label, $metric['label']);
                $this->assertSame($unit, $metric['unit']);
                $this->assertEqualsWithDelta($expected, $metric['value'], .000001);
                $this->assertSame($date, $entry['date']);
                $this->assertStringNotContainsString('private device', json_encode($entry));
            }
        }
        Http::assertNothingSent();
    }

    public function test_richer_export_kept_and_duplicate_oura_scalars_removed_while_retries_keep_workouts(): void
    {
        $this->export('current', ['workouts' => [$this->workout('Oura')]]);
        $entry = $this->capture()['entries']['workouts']['2026-09-15'];
        $this->assertCount(1, $entry['providers']['apple_health']['workouts']);
        $partial = $entry;
        $partial['providers']['apple_health']['workouts'][0]['metrics'] = array_values(array_filter($partial['providers']['apple_health']['workouts'][0]['metrics'], fn ($m) => $m['key'] === 'apple_health.workout.duration'));
        $partial['providers']['apple_health']['workouts'][0]['series'] = [];
        $kept = EntryContract::merge($entry, $partial);
        $this->assertSame(EntryContract::fingerprint($entry), EntryContract::fingerprint($kept));
        $incoming = $entry;
        $incoming['providers']['apple_health']['workouts'][0]['end'] = '2026-09-15T19:53:09+03:00';
        $merged = EntryContract::merge($entry, $incoming);
        $this->assertCount(1, $merged['providers']['apple_health']['workouts']);
        $oura = ['fetched_at' => now()->toIso8601String(), 'metrics' => [['key' => 'oura.workout.duration', 'value' => 420, 'at' => '2026-09-15T19:45:09+03:00']], 'series' => []];
        $incoming['providers']['oura'] = $oura;
        $merged = EntryContract::merge($entry, $incoming);
        $this->assertArrayNotHasKey('oura', $merged['providers']);
        $this->assertCount(1, $merged['providers']['apple_health']['workouts']);
        $this->assertSame(EntryContract::fingerprint($merged), EntryContract::fingerprint(EntryContract::merge($entry, $merged)));
        $entry['providers']['apple_health']['workouts'][0]['origin'] = 'apple_health';
        $incoming = $entry;
        unset($incoming['providers']['apple_health']);
        $incoming['providers']['oura'] = $oura;
        $this->assertCount(1, EntryContract::merge($entry, $incoming)['providers']['apple_health']['workouts']);
    }

    public function test_preview_freezes_folder_and_publish_uses_reviewed_data_without_rescanning(): void
    {
        $this->export('first', ['metrics' => [$this->metric()]], 100);
        $response = $this->postJson('/health/previews', ['destination' => 'local'])->assertOk()->assertJsonPath('apple_health.folder', 'first');
        $id = $response->json('id');
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $this->export('second', ['metrics' => [$this->metric(value: 999)]], 200);
        foreach (Collector::tasks() as $task) {
            if (str_starts_with($task, 'apple_health.')) {
                $this->postJson('/health/previews/'.$id.'/fetch/'.$task)->assertOk();
            } else {
                Cache::put('health:task:'.$id.':'.$task, ['state' => 'empty', 'message' => 'Fixture', 'entries' => []]);
            }
        }
        Http::fake(fn ($r) => Http::response($r->method() === 'GET' ? ['schema_version' => 1, 'entry' => null, 'revision' => null] : ['schema_version' => 1, 'operation' => 'created', 'url' => 'https://blog.test/health/fixture', 'revision' => str_repeat('a', 64), 'id' => 1]));
        $this->postJson('/health/previews/'.$id.'/prepare')->assertOk()->assertJsonPath('entries.activity:2026-09-16.entry.providers.apple_health.metrics.0.value', 123);
        Http::assertSentCount(1);
        $this->postJson('/health/previews/'.$id.'/publish', ['destination' => 'local', 'entry' => 'activity:2026-09-16'])->assertOk();
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['providers']['apple_health']['metrics'][0]['value'] === 123.0);
        $this->deleteJson('/health/previews/'.$id)->assertNoContent();
        $this->assertNull(Cache::get('health:apple:'.$id));
    }
}
