<?php

namespace App\Actions\Boards;

use App\Models\Board;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ArchiveBoard
{
    public function execute(User $user, Board $board, bool $archived): Board
    {
        if (! $board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'board' => 'Non hai accesso a questa board.',
            ]);
        }

        $board->update(['archived' => $archived]);

        return $board->fresh();
    }
}
