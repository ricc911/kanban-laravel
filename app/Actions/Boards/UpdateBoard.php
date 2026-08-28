<?php

namespace App\Actions\Boards;

use App\Models\Board;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateBoard
{
    public function execute(User $user, Board $board, string $name, ?string $description = null, ?string $color = null): Board
    {
        if (! $board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'board' => 'Non hai accesso a questa board.',
            ]);
        }

        $board->update([
            'name' => trim($name),
            'description' => $description,
            'color' => $color,
        ]);

        return $board->fresh();
    }
}
