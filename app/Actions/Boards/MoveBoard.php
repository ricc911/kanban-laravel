<?php

namespace App\Actions\Boards;

use App\Actions\Activity\LogActivity;
use App\Models\Board;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveBoard
{
    public function __construct(private LogActivity $logger) {}

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

        $board->loadMissing('folder');
        $fromFolder = $board->folder;

        DB::transaction(function () use ($user, $board, $folder, $archived, $fromFolder): void {
            $updates = ['folder_id' => $folder?->id];

            if ($archived !== null) {
                $updates['archived'] = $archived;
            }

            $board->update($updates);

            if ($fromFolder?->id !== $folder?->id) {
                $this->logger->execute($user, $board->workspace, 'board.moved', $board, $board, [
                    'board_name' => $board->name,
                    'from_folder_id' => $fromFolder?->id,
                    'from_folder_name' => $fromFolder?->name,
                    'to_folder_id' => $folder?->id,
                    'to_folder_name' => $folder?->name,
                ]);
            }
        });

        return $board->fresh();
    }
}
