<?php

namespace App\Actions\BoardColumns;

use App\Models\BoardColumn;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MoveBoardColumn
{
    public function execute(
        User $user,
        BoardColumn $column,
        int $position
    ): BoardColumn {
        if (! $column->board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'column' => 'Non hai accesso a questa colonna.',
            ]);
        }

        $column->update([
            'position' => max(0, $position),
        ]);

        return $column->fresh();
    }
}
