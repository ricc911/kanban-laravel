<?php

namespace App\Actions\BoardColumns;

use App\Actions\Activity\LogActivity;
use App\Models\BoardColumn;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteBoardColumn
{
    public function __construct(private LogActivity $logger) {}

    public function execute(
        User $user,
        BoardColumn $column
    ): void {
        if (! $column->board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'column' => 'Non hai accesso a questa colonna.',
            ]);
        }

        if ($column->tasks()->exists()) {
            throw ValidationException::withMessages([
                'column' => 'Sposta o elimina le task prima di eliminare la colonna.',
            ]);
        }

        if ($column->board->columns()->count() <= 1) {
            throw ValidationException::withMessages([
                'column' => 'La board deve avere almeno una colonna.',
            ]);
        }

        $board = $column->board;
        $name = $column->name;
        $column->delete();
        $this->logger->execute($user, $board->workspace, 'column.deleted', $board, null, ['column_name' => $name]);
    }
}
