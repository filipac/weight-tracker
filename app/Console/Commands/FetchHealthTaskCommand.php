<?php

namespace App\Console\Commands;

use App\Health\PublishingWorkflow;
use Illuminate\Console\Command;

class FetchHealthTaskCommand extends Command
{
    protected $signature = 'health:fetch-task {id} {task}';

    protected $description = 'Internal worker for a single frozen health preview collection';

    protected $hidden = true;

    public function handle(PublishingWorkflow $workflow): int
    {
        $owner = getenv('HEALTH_PREVIEW_OWNER');
        if (! is_string($owner) || ! preg_match('/^[a-f0-9]{64}$/D', $owner)) {
            return self::FAILURE;
        }
        // Each worker is a fresh PHP process and does not inherit ini_set().
        ini_set('memory_limit', '-1');
        try {
            $result = $workflow->fetch($this->argument('id'), $owner, $this->argument('task'));
            $this->output->write(json_encode($result, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (\Throwable) {
            // Do not echo exceptions, credentials or raw responses into the terminal.
            return self::FAILURE;
        }
    }
}
