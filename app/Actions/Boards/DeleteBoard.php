<?php

namespace App\Actions\Boards;

use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteBoard
{
    public function execute(User $user, Board $board): void
    {
        if (! $board->workspace->hasMember($user)) {
            throw ValidationException::withMessages(['board' => 'Non hai accesso a questa board.']);
        }

        DB::transaction(fn (): bool => $board->delete());
    }
}
