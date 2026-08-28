<?php

namespace App\Actions\BoardColumns;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateBoardColumn
{
    public function execute(
        User $user,
        Board $board,
        string $name
    ): BoardColumn {
        if (!$board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'board' => 'Non hai accesso a questa board.',
            ]);
        }

        $position = (($board->columns()->max('position') ?? 0) + 1000);

        return $board->columns()->create([
            'name' => trim($name),
            'position' => $position,
        ]);
    }
}
