<?php

namespace App\Actions\Boards;

use App\Actions\Activity\LogActivity;
use App\Events\BoardChanged;
use App\Models\Board;
use App\Models\User;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteBoard
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, Board $board): void
    {
        if (! $board->workspace->canEditContent($user)) {
            throw ValidationException::withMessages(['board' => 'Non hai accesso a questa board.']);
        }

        DB::transaction(function () use ($user, $board): void {
            $workspace = $board->workspace;
            $name = $board->name;
            $boardId = (int) $board->id;
            $boardPayload = RealtimePayload::board($board);
            $this->logger->execute($user, $workspace, 'board.deleted', $board, $board, ['board_name' => $name]);
            $board->delete();
            BoardChanged::dispatch('board.deleted', (int) $workspace->id, $boardId, [
                'board' => $boardPayload,
            ], true);
        });
    }
}
