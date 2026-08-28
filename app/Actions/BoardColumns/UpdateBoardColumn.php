<?php

namespace App\Actions\BoardColumns;

use App\Models\BoardColumn;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateBoardColumn
{
    public function execute(
        User $user,
        BoardColumn $column,
        string $name
    ): BoardColumn {
        if (!$column->board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'column' => 'Non hai accesso a questa colonna.',
            ]);
        }

        $column->update([
            'name' => trim($name),
        ]);

        return $column->fresh();
    }
}
