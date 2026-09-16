<?php

namespace App\Health;

/** Local console mutex: the OS releases it even after SIGKILL or a crash. */
class ConsoleRunLock
{
    private mixed $stream = null;

    public function __construct(private ?string $path = null) {}

    public function get(): bool
    {
        if (is_resource($this->stream)) {
            return false;
        }
        // Never truncate or unlink this file: concurrent callers must lock the
        // same inode. Close-on-exec keeps fetch children from inheriting the lock.
        $stream = @fopen($this->path ?? config('health.console_lock_path', storage_path('framework/health-publish.lock')), 'c+e');
        if ($stream === false) {
            throw new ProviderException('configuration', 'Cannot open the health publishing lock. Check storage/framework permissions.');
        }
        if (! flock($stream, LOCK_EX | LOCK_NB, $blocked)) {
            fclose($stream);
            if (! $blocked) {
                throw new ProviderException('configuration', 'Cannot lock the health publishing file. Check storage/framework permissions.');
            }

            return false;
        }
        $this->stream = $stream;

        return true;
    }

    public function release(): void
    {
        if (is_resource($this->stream)) {
            flock($this->stream, LOCK_UN);
            fclose($this->stream);
            $this->stream = null;
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
