<?php

namespace App\Actions\BoardColumns;

use App\Actions\Activity\LogActivity;
use App\Events\BoardChanged;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\User;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateBoardColumn
{
    public function __construct(private LogActivity $logger) {}

    public function execute(
        User $user,
        Board $board,
        string $name
    ): BoardColumn {
        if (! $board->workspace->canEditContent($user)) {
            throw ValidationException::withMessages([
                'board' => 'Non hai accesso a questa board.',
            ]);
        }

        return DB::transaction(function () use ($user, $board, $name): BoardColumn {
            $position = (($board->columns()->max('position') ?? 0) + 1000);

            $column = $board->columns()->create([
                'name' => trim($name),
                'position' => $position,
            ]);
            $this->logger->execute($user, $board->workspace, 'column.created', $board, $column, ['column_name' => $column->name]);
            BoardChanged::dispatch('column.created', (int) $board->workspace_id, (int) $board->id, [
                'column' => RealtimePayload::column($column),
            ]);

            return $column;
        });
    }
}
