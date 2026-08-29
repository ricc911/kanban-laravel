<?php

namespace App\Actions\BoardColumns;

use App\Actions\Activity\LogActivity;
use App\Events\BoardChanged;
use App\Models\BoardColumn;
use App\Models\User;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
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

        DB::transaction(function () use ($user, $column): void {
            $board = $column->board;
            $payload = RealtimePayload::column($column);
            $this->logger->execute($user, $board->workspace, 'column.deleted', $board, $column, ['column_name' => $column->name]);
            $column->delete();
            BoardChanged::dispatch('column.deleted', (int) $board->workspace_id, (int) $board->id, [
                'column_id' => (int) $column->id,
                'column' => $payload,
            ]);
        });
    }
}
