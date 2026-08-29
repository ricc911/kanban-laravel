<?php

namespace App\Actions\BoardColumns;

use App\Actions\Activity\LogActivity;
use App\Events\BoardChanged;
use App\Models\BoardColumn;
use App\Models\User;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateBoardColumn
{
    public function __construct(private LogActivity $logger) {}

    public function execute(
        User $user,
        BoardColumn $column,
        string $name
    ): BoardColumn {
        if (! $column->board->workspace->canEditContent($user)) {
            throw ValidationException::withMessages([
                'column' => 'Non hai accesso a questa colonna.',
            ]);
        }

        $changes = $column->name !== trim($name) ? ['name' => ['old' => $column->name, 'new' => trim($name)]] : [];

        return DB::transaction(function () use ($user, $column, $name, $changes): BoardColumn {
            $column->update([
                'name' => trim($name),
            ]);
            $freshColumn = $column->fresh();
            if ($changes) {
                $this->logger->execute($user, $column->board->workspace, 'column.updated', $column->board, $freshColumn, ['column_name' => $freshColumn->name, 'changes' => $changes]);
                BoardChanged::dispatch('column.updated', (int) $column->board->workspace_id, (int) $column->board_id, [
                    'column' => RealtimePayload::column($freshColumn),
                ]);
            }

            return $freshColumn;
        });
    }
}
