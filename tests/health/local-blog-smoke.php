<?php

/** Opt-in synthetic smoke data for blog.test only. Run seed, then cleanup after visual checks. */
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$client = app(App\Health\BlogClient::class);
$connection = $client->connection('local');
if (rtrim($connection['url'], '/') !== 'https://blog.test') {
    throw new RuntimeException('Smoke tests only run against https://blog.test.');
}
$manifest = storage_path('framework/testing/health-blog-smoke.json');
if (! is_dir(dirname($manifest))) {
    mkdir(dirname($manifest), 0700, true);
}
$http = fn () => Illuminate\Support\Facades\Http::withBasicAuth($connection['username'], $connection['password'])->timeout(20)->withoutRedirecting();
if (($argv[1] ?? '') === 'cleanup') {
    foreach (json_decode(file_get_contents($manifest), true) as $record) {
        $response = $http()->get($connection['url'].'/wp-json/wp/v2/health-entries/'.$record['id']);
        if ($response->status() === 404) {
            continue;
        }
        if ($response->json('health_data.date') !== '2001-01-03' || $response->json('health_data.topic') !== $record['topic']) {
            throw new RuntimeException('Fixture identity changed; refusing cleanup.');
        }
        if (! $http()->delete($connection['url'].'/wp-json/wp/v2/health-entries/'.$record['id'], ['force' => true])->successful()) {
            throw new RuntimeException('Fixture cleanup failed.');
        }
    }
    unlink($manifest);
    echo "Synthetic local fixtures removed.\n";
    exit;
}
if (($argv[1] ?? '') !== 'seed') {
    throw new RuntimeException('Use seed or cleanup.');
}
if (is_file($manifest)) {
    throw new RuntimeException('A fixture manifest already exists. Clean it up before seeding.');
}
$fixtures = [
    'weight' => ['withings.measure.1' => 80.2],
    'body-composition' => ['withings.measure.6' => 24.5, 'withings.measure.76' => 56.4, 'withings.measure.77' => 42.8, 'withings.measure.88' => 3.1],
    'activity' => ['oura.daily_activity.steps' => 8452, 'oura.daily_activity.active_calories' => 428, 'oura.daily_activity.score' => 88, 'withings.activity.steps' => 8210],
    'heart' => ['oura.sleep.average_heart_rate' => 58, 'oura.sleep.average_hrv' => 42, 'withings.measure.11' => 62],
    'sleep' => ['oura.daily_sleep.score' => 86, 'oura.sleep.total_sleep_duration' => 27000, 'oura.sleep.efficiency' => 91],
    'recovery' => ['oura.daily_readiness.score' => 82, 'oura.daily_stress.stress_high' => 1800, 'oura.daily_stress.recovery_high' => 3600],
    'vitals' => ['oura.daily_spo2.spo2_percentage.average' => 97.5, 'oura.sleep.average_breath' => 14.2],
    'mindfulness' => ['oura.session.duration' => 900],
];
$records = [];
foreach ($fixtures as $topic => $metrics) {
    $existing = $client->read('local', $topic, '2001-01-03');
    if (! empty($existing['entry'])) {
        throw new RuntimeException('Fixture day is occupied; refusing to overwrite.');
    }
    $entry = ['schema_version' => 1, 'topic' => $topic, 'date' => '2001-01-03', 'timezone' => 'Europe/Bucharest', 'providers' => []];
    foreach ($metrics as $key => $value) {
        $def = App\Health\MetricCatalog::all()[$key];
        $source = $def['source'];
        $entry['providers'][$source] ??= ['fetched_at' => '2001-01-04T10:00:00+02:00', 'metrics' => [], 'series' => []];
        $entry['providers'][$source]['metrics'][] = ['key' => $key, 'value' => $value, 'at' => '2001-01-03T08:00:00+02:00'];
    }
    if ($topic === 'heart') {
        $points = [];
        for ($i = 0; $i < 100; $i++) {
            $points[] = ['at' => Carbon\CarbonImmutable::parse('2001-01-03T08:00:00+02:00')->addMinutes(5 * $i)->toIso8601String(), 'value' => 62 + round(8 * sin($i / 6), 1)];
        }
        $entry['providers']['oura']['series'][] = ['key' => 'oura.heartrate.bpm', 'points' => $points];
    }
    if ($topic === 'activity') {
        $entry['providers']['apple_health'] = ['fetched_at' => '2001-01-04T10:00:00+02:00', 'metrics' => [], 'series' => [], 'workouts' => []];
        foreach (['outdoor-run', 'indoor-run'] as $i => $type) {
            $at = Carbon\CarbonImmutable::parse('2001-01-03T12:00:00+02:00')->addHours($i * 2);
            $points = [];
            for ($j = 0; $j < 20; $j++) {
                $points[] = ['at' => $at->addMinutes($j)->toIso8601String(), 'value' => 120 + 20 * sin($j / 4)];
            }
            $entry['providers']['apple_health']['workouts'][] = ['type' => $type, 'origin' => 'apple_health', 'start' => $at->toIso8601String(), 'end' => $at->addMinutes(20)->toIso8601String(),
                'metrics' => [['key' => 'apple_health.workout.distance', 'value' => 2.5, 'at' => $at->toIso8601String()], ['key' => 'apple_health.workout.duration', 'value' => 1200, 'at' => $at->toIso8601String()], ['key' => 'apple_health.workout.activeEnergyBurned', 'value' => 150, 'at' => $at->toIso8601String()]],
                'series' => [['key' => 'apple_health.workout.heartRateData.Avg', 'points' => $points]]];
        }
    }
    $entry = App\Health\EntryContract::normalize($entry);
    $result = $client->publish('local', $entry, null);
    if ($result['operation'] !== 'created') {
        throw new RuntimeException('Expected new fixture.');
    }
    $records[] = ['id' => $result['id'], 'topic' => $topic];
    file_put_contents($manifest, json_encode($records));
    if ($client->publish('local', $entry, null)['operation'] !== 'unchanged') {
        throw new RuntimeException('Duplicate prevention failed.');
    }
    echo 'Verified create and retry: '.$result['url']."\n";
}
echo "Eight synthetic entries ready for visual inspection. Run cleanup afterward.\n";
