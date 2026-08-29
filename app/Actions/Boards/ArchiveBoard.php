<?php

namespace App\Actions\Boards;

use App\Actions\Activity\LogActivity;
use App\Models\Board;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ArchiveBoard
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, Board $board, bool $archived): Board
    {
        if (! $board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'board' => 'Non hai accesso a questa board.',
            ]);
        }

        $board->update(['archived' => $archived]);
        $this->logger->execute($user, $board->workspace, $archived ? 'board.archived' : 'board.restored', $board, $board, ['board_name' => $board->name]);

        return $board->fresh();
    }
}
