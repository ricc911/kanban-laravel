<?php

namespace App\Actions\Tasks;

use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MoveTask
{
    public function execute(
        User $user,
        Task $task,
        BoardColumn $targetColumn,
        int $position
    ): Task {
        if (! $task->board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'task' => 'Non hai accesso a questa task.',
            ]);
        }

        if ($targetColumn->board_id !== $task->board_id) {
            throw ValidationException::withMessages([
                'column' => 'Non puoi spostare la task in una colonna di un’altra board.',
            ]);
        }

        $task->update([
            'board_column_id' => $targetColumn->id,
            'position' => max(0, $position),
        ]);

        return $task->fresh();
    }
}
