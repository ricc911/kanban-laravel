<?php

namespace App\Actions\Boards;

use App\Models\Board;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveBoard
{
    public function execute(User $user, Board $board, ?Folder $folder = null, ?bool $archived = null): Board
    {
        if (! $board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'board' => 'Non hai accesso a questa board.',
            ]);
        }

        if ($folder !== null && $folder->workspace_id !== $board->workspace_id) {
            throw ValidationException::withMessages([
                'folder' => 'La cartella appartiene a un altro workspace.',
            ]);
        }

        DB::transaction(function () use ($board, $folder, $archived): void {
            $updates = ['folder_id' => $folder?->id];

            if ($archived !== null) {
                $updates['archived'] = $archived;
            }

            $board->update($updates);
        });

        return $board->fresh();
    }
}
