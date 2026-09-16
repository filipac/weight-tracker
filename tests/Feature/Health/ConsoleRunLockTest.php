<?php

namespace Tests\Feature\Health;

use App\Health\ConsoleRunLock;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConsoleRunLockTest extends TestCase
{
    public static function stopSignals(): array
    {
        return ['interrupt' => [SIGINT], 'force kill' => [SIGKILL]];
    }

    #[DataProvider('stopSignals')]
    public function test_active_runs_are_blocked_and_stopped_runs_release_immediately(int $signal): void
    {
        $path = tempnam(sys_get_temp_dir(), 'health-lock-test-');
        $process = new Process([PHP_BINARY, base_path('tests/health/console-lock-worker.php'), $path]);
        $process->setTimeout(10);
        $contender = new ConsoleRunLock($path);
        try {
            $process->start();
            $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'LOCKED')));
            $this->assertFalse($contender->get());
            $this->travel(2)->hours();
            $this->assertFalse($contender->get(), 'A running process must not lose its lock to a TTL.');
            $process->signal($signal);
            try {
                $process->wait();
            } catch (\Symfony\Component\Process\Exception\ProcessSignaledException) {
                // Expected: the test deliberately interrupts/kills its own fixture.
            }
            $this->assertTrue($contender->get(), 'A stopped process must not leave a stale lock.');
            $contender->release();
            $this->assertFileExists($path, 'The stable lock file must never be unlinked on release.');
            $this->assertTrue($contender->get());
        } finally {
            $process->stop(0);
            $contender->release();
            unlink($path);
        }
    }
}
