<?php

namespace App\Actions\BoardColumns;

use App\Actions\Activity\LogActivity;
use App\Models\BoardColumn;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateBoardColumn
{
    public function __construct(private LogActivity $logger) {}

    public function execute(
        User $user,
        BoardColumn $column,
        string $name
    ): BoardColumn {
        if (! $column->board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'column' => 'Non hai accesso a questa colonna.',
            ]);
        }

        $changes = $column->name !== trim($name) ? ['name' => ['old' => $column->name, 'new' => trim($name)]] : [];
        $column->update([
            'name' => trim($name),
        ]);
        if ($changes) {
            $this->logger->execute($user, $column->board->workspace, 'column.updated', $column->board, $column, ['column_name' => $column->name, 'changes' => $changes]);
        }

        return $column->fresh();
    }
}
