<?php

namespace App\Actions\Tasks;

use App\Actions\Activity\LogActivity;
use App\Events\TaskMoved;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

        $board = $task->board;
        $fromColumn = $task->column;
        $newPosition = max(0, $position);
        $columnChanged = $fromColumn->id !== $targetColumn->id;
        $positionChanged = (int) $task->position !== $newPosition;

        return DB::transaction(function () use ($user, $task, $board, $fromColumn, $targetColumn, $newPosition, $columnChanged, $positionChanged): Task {
            $task->update([
                'board_column_id' => $targetColumn->id,
                'position' => $newPosition,
            ]);
            $freshTask = $task->fresh();
            if ($columnChanged) {
                $this->logger->execute($user, $board->workspace, 'task.moved', $board, $freshTask, [
                    'task_title' => $freshTask->title,
                    'from_column_id' => $fromColumn->id,
                    'from_column_name' => $fromColumn->name,
                    'to_column_id' => $targetColumn->id,
                    'to_column_name' => $targetColumn->name,
                ]);
            }
            if ($columnChanged || $positionChanged) {
                TaskMoved::dispatch($freshTask);
            }

            return $freshTask;
        });
    }
}
