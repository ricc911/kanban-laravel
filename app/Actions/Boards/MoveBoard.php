<?php

namespace App\Actions\Boards;

use App\Actions\Activity\LogActivity;
use App\Events\BoardChanged;
use App\Models\Board;
use App\Models\Folder;
use App\Models\User;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MoveBoard
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, Board $board, ?Folder $folder = null, ?bool $archived = null): Board
    {
        if (! $board->workspace->canEditContent($user)) {
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
        $fromArchived = (bool) $board->archived;

        DB::transaction(function () use ($user, $board, $folder, $archived, $fromFolder, $fromArchived): void {
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
                BoardChanged::dispatch('board.moved', (int) $board->workspace_id, (int) $board->id, [
                    'board' => RealtimePayload::board($board->fresh()),
                ], true);
            }

            if ($archived !== null && $fromArchived !== (bool) $archived) {
                $freshBoard = $board->fresh();
                $action = $archived ? 'board.archived' : 'board.restored';
                $this->logger->execute($user, $board->workspace, $action, $freshBoard, $freshBoard, ['board_name' => $freshBoard->name]);
                BoardChanged::dispatch($action, (int) $board->workspace_id, (int) $board->id, [
                    'board' => RealtimePayload::board($freshBoard),
                ], true);
            }
        });

        return $board->fresh();
    }
}
