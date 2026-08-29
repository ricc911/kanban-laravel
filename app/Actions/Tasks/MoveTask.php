<?php

namespace App\Actions\Tasks;

use App\Actions\Activity\LogActivity;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class MoveTask
{
    public function __construct(private LogActivity $logger) {}

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

        $fromColumn = $task->column;
        $task->update([
            'board_column_id' => $targetColumn->id,
            'position' => max(0, $position),
        ]);
        if ($fromColumn->id !== $targetColumn->id) {
            $this->logger->execute($user, $task->board->workspace, 'task.moved', $task->board, $task, [
                'task_title' => $task->title,
                'from_column_id' => $fromColumn->id,
                'from_column_name' => $fromColumn->name,
                'to_column_id' => $targetColumn->id,
                'to_column_name' => $targetColumn->name,
            ]);
        }

        return $task->fresh();
    }
}
