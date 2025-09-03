<?php

namespace App\Console\Commands;

use App\Services\NotesAppService;
use Illuminate\Console\Command;

class GetWeightNoteCommand extends Command
{
    protected $signature = 'notes:weight';

    protected $description = 'Get the contents of the "weight" note from Notes.app';

    public function handle(NotesAppService $notesService)
    {
        $this->info('Retrieving "weight" note from Notes.app...');

        try {
            $noteContent = $notesService->getNoteByName('weight');

            if ($noteContent === null) {
                $this->error('Note "weight" not found in Notes.app');

                return 1;
            }

            $this->newLine();
            $this->info('Contents of "weight" note:');
            $this->newLine();
            $this->line($noteContent);
            $this->newLine();

            return 0;
        } catch (\Exception $e) {
            $this->error('Error retrieving note: '.$e->getMessage());

            return 1;
        }
    }
}
