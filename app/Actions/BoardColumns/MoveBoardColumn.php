<?php

namespace App\Actions\BoardColumns;

use App\Events\BoardChanged;
use App\Models\BoardColumn;
use App\Models\User;
use App\Support\RealtimePayload;
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

        $newPosition = max(0, $position);
        if ((int) $column->position !== $newPosition) {
            $column->update(['position' => $newPosition]);
            BoardChanged::dispatch('column.updated', (int) $column->board->workspace_id, (int) $column->board_id, [
                'column' => RealtimePayload::column($column->fresh()),
            ]);
        }

        return $column->fresh();
    }
}
