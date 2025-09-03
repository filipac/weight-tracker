<?php

namespace App\Services;

class NotesAppService
{
    public function getNoteByName(string $noteName): ?string
    {
        $appleScript = sprintf(
            'tell application "Notes"
    try
        set targetNote to first note whose name is "%s"
        get body of targetNote
    on error
        return ""
    end try
end tell',
            addslashes($noteName)
        );

        $result = shell_exec("osascript -e '$appleScript'");

        if ($result === null || trim($result) === '') {
            return null;
        }

        return trim($result);
    }

    public function createNote(string $noteContent, ?string $noteName = null): bool
    {
        $properties = $noteName
            ? sprintf('{body:"%s", name:"%s"}', addslashes($noteContent), addslashes($noteName))
            : sprintf('{body:"%s"}', addslashes($noteContent));

        $appleScript = sprintf(
            'tell application "Notes"
    make new note with properties %s
end tell',
            $properties
        );

        $result = shell_exec("osascript -e '$appleScript'");

        return $result !== null;
    }

    public function updateNote(string $noteName, string $newContent): bool
    {
        $appleScript = sprintf(
            'tell application "Notes"
    try
        set targetNote to first note whose name is "%s"
        set body of targetNote to "%s"
        return true
    on error
        return false
    end try
end tell',
            addslashes($noteName),
            addslashes($newContent)
        );

        $result = shell_exec("osascript -e '$appleScript'");

        return trim($result) === 'true';
    }

    public function listAllNotes(): array
    {
        $appleScript = 'tell application "Notes"
    set noteNames to {}
    repeat with eachNote in every note
        set end of noteNames to name of eachNote
    end repeat
    return noteNames as string
end tell';

        $result = shell_exec("osascript -e '$appleScript'");

        if ($result === null || trim($result) === '') {
            return [];
        }

        return array_filter(explode(', ', trim($result)));
    }

    public function noteExists(string $noteName): bool
    {
        $appleScript = sprintf(
            'tell application "Notes"
    try
        set targetNote to first note whose name is "%s"
        return true
    on error
        return false
    end try
end tell',
            addslashes($noteName)
        );

        $result = shell_exec("osascript -e '$appleScript'");

        return trim($result) === 'true';
    }
}
