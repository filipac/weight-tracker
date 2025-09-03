<?php

namespace App\Console\Commands;

use App\Actions\GenerateWeightListAction;
use App\Services\NotesAppService;
use Illuminate\Console\Command;

class UpdateWeightNoteCommand extends Command
{
    protected $signature = 'notes:update-weight';

    protected $description = 'Update the weight note by replacing content between == start and == end with database entries';

    public function handle(NotesAppService $notesService, GenerateWeightListAction $action)
    {
        $this->info('Updating "weight" note in Notes.app...');

        try {
            // Get current note content
            $currentContent = $notesService->getNoteByName('weight');

            if ($currentContent === null) {
                $this->error('Note "weight" not found in Notes.app');

                return 1;
            }

            // Generate new weight list
            $weightList = $action->execute();

            if (empty($weightList)) {
                $this->error('No weight entries found in database');

                return 1;
            }

            // Wrap each line in div tags for Notes.app compatibility
            $wrappedContent = array_map(function ($line) {
                return '<div>'.htmlspecialchars($line, ENT_QUOTES, 'UTF-8').'</div>';
            }, $weightList);

            // Find start and end markers
            $startMarker = '<div><span style="font-size: 16px">== start</span></div>';
            $endMarker = '<div><span style="font-size: 16px">== end</span></div>';

            $startPos = strpos($currentContent, $startMarker);
            $endPos = strpos($currentContent, $endMarker);

            // dd($startPos, $endPos, $currentContent);

            if ($startPos === false || $endPos === false) {
                $this->error('Could not find == start or == end markers in the note');

                return 1;
            }

            // Calculate positions after the start marker and before the end marker
            $contentStart = $startPos + strlen($startMarker);
            $contentEnd = $endPos;

            // Build new content
            $beforeStart = substr($currentContent, 0, $contentStart);
            $afterEnd = substr($currentContent, $contentEnd);

            $newContent = $beforeStart."\n".
                         '<div><br></div>'."\n".
                         implode("\n", $wrappedContent)."\n".
                         '<div><br></div>'."\n".
                         $afterEnd;

            // Update the note
            $success = $notesService->updateNote('weight', $newContent);

            if ($success) {
                $this->info('Successfully updated "weight" note with '.count($weightList).' entries');

                return 0;
            } else {
                $this->error('Failed to update the note');

                return 1;
            }

        } catch (\Exception $e) {
            $this->error('Error updating note: '.$e->getMessage());

            return 1;
        }
    }
}
