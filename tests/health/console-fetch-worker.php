<?php

// Isolated child-process fixture. Never use the personal cache or provider APIs.
$cacheDirectory = getenv('HEALTH_TEST_CACHE');
if (getenv('APP_ENV') !== 'testing' || ! is_string($cacheDirectory)
    || ! str_starts_with($cacheDirectory, sys_get_temp_dir().'/health-console-test-') || ! is_dir($cacheDirectory)) {
    exit(2);
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['cache.default' => 'file', 'cache.stores.file.path' => $cacheDirectory, 'cache.stores.file.lock_path' => $cacheDirectory]);
Illuminate\Support\Facades\Http::preventStrayRequests();
$app->instance(App\Health\Collector::class, new class extends App\Health\Collector
{
    public function __construct() {}

    public function fetch(string $task, string $today, string $yesterday, string $fetchedAt, array $appleSnapshot = []): array
    {
        $cache = Illuminate\Support\Facades\Cache::getFacadeRoot();
        $cache->lock('test:counter-lock', 10)->block(5, function () use ($cache, $task, $today, $yesterday, $fetchedAt) {
            $active = $cache->get('test:active', 0) + 1;
            $cache->put('test:active', $active, 60);
            $cache->put('test:peak', max($active, $cache->get('test:peak', 0)), 60);
            $calls = $cache->get('test:calls', []);
            $calls[] = [$task, $today, $yesterday, $fetchedAt];
            $cache->put('test:calls', $calls, 60);
        });
        // Keep the first batch alive until all workers enter the collector;
        // PHP startup and file-lock scheduling must not make overlap assertions flaky.
        $deadline = microtime(true) + 5;
        while ($cache->get('test:peak', 0) < $cache->get('test:barrier', 1) && microtime(true) < $deadline) {
            usleep(10_000);
        }
        usleep(500_000);
        $cache->lock('test:counter-lock', 10)->block(5, fn () => $cache->decrement('test:active'));
        if ($task === $cache->get('test:crash')) {
            exit(7);
        }
        if ($task === $cache->get('test:failure')) {
            throw new App\Health\ProviderException('rate_limited', 'Fixture rate limit');
        }

        $entries = $task === 'withings.weigh_in' ? $cache->get('test:entries', []) : [];

        return ['state' => $entries ? 'ok' : 'empty', 'message' => 'Fixture completed', 'entries' => $entries];
    }
});
exit($app->handleCommand(new Symfony\Component\Console\Input\ArgvInput($argv)));
