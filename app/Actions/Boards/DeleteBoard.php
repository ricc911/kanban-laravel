<?php

namespace App\Actions\Boards;

use App\Actions\Activity\LogActivity;
use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteBoard
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, Board $board): void
    {
        if (! $board->workspace->hasMember($user)) {
            throw ValidationException::withMessages(['board' => 'Non hai accesso a questa board.']);
        }

        DB::transaction(function () use ($user, $board): void {
            $workspace = $board->workspace;
            $name = $board->name;
            $board->delete();
            $this->logger->execute($user, $workspace, 'board.deleted', $board, null, ['board_name' => $name]);
        });
    }
}
