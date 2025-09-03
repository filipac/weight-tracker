<?php

namespace App\Console\Commands;

use App\Actions\GenerateWeightListAction;
use Illuminate\Console\Command;

class GenerateWeightListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'weight:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate weight tracking list in Notes.app format';

    /**
     * Execute the console command.
     */
    public function handle(GenerateWeightListAction $action)
    {
        $weightList = $action->execute();

        if (empty($weightList)) {
            $this->info('No weight entries found.');

            return;
        }

        $this->info('Weight tracking list:');
        $this->newLine();

        foreach ($weightList as $entry) {
            $this->line($entry);
        }

        $this->newLine();
        $this->info('Total entries: '.count($weightList));
    }
}
