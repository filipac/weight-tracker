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
            $weightList = $action->execute(reverse: true);

            if (empty($weightList)) {
                $this->error('No weight entries found in database');

                return 1;
            }

            // Wrap each line in div tags for Notes.app compatibility
            $wrappedContent = array_map(function ($line) {
                return '<div>'.htmlspecialchars($line, ENT_QUOTES, 'UTF-8').'</div>';
            }, $weightList);

            // Notes.app may change the HTML around text, so match the marker line by text.
            $startMarker = $this->findMarker($currentContent, 'start');
            $endMarker = $this->findMarker($currentContent, 'end');

            if ($startMarker === null || $endMarker === null) {
                $this->error('Could not find == start or == end markers in the note');

                return 1;
            }

            // Calculate positions after the start marker and before the end marker
            $contentStart = $startMarker['offset'] + strlen($startMarker['html']);
            $contentEnd = $endMarker['offset'];

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

    private function findMarker(string $content, string $marker): ?array
    {
        $pattern = sprintf(
            '/<div\b[^>]*>\s*(?:<span\b[^>]*>\s*)?==\s*%s\s*(?:<\/span>\s*)?<\/div>/i',
            preg_quote($marker, '/')
        );

        if (! preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        return [
            'html' => $matches[0][0],
            'offset' => $matches[0][1],
        ];
    }
}
