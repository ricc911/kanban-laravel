<?php

namespace App\Actions\Boards;

use App\Actions\Activity\LogActivity;
use App\Events\BoardChanged;
use App\Models\Board;
use App\Models\User;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchiveBoard
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, Board $board, bool $archived): Board
    {
        if (! $board->workspace->canEditContent($user)) {
            throw ValidationException::withMessages([
                'board' => 'Non hai accesso a questa board.',
            ]);
        }

        return DB::transaction(function () use ($user, $board, $archived): Board {
            if ((bool) $board->archived === $archived) {
                return $board->fresh();
            }

            $board->update(['archived' => $archived]);
            $freshBoard = $board->fresh();
            $action = $archived ? 'board.archived' : 'board.restored';
            $this->logger->execute($user, $board->workspace, $action, $freshBoard, $freshBoard, ['board_name' => $freshBoard->name]);
            BoardChanged::dispatch($action, (int) $board->workspace_id, (int) $board->id, [
                'board' => RealtimePayload::board($freshBoard),
            ], true);

            return $freshBoard;
        });
    }
}
