<?php

namespace App\Actions\Tasks;

use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReorderTasks
{
    public function execute(
        User $user,
        BoardColumn $column,
        array $taskIds
    ): void {
        if (!$column->board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'column' => 'Non hai accesso a questa colonna.',
            ]);
        }

        $tasks = Task::whereIn('id', $taskIds)->get();

        $existingTaskIds = $column->tasks()
            ->pluck('id')
            ->map(fn($id) => (int) $id);

        $requestedTaskIds = collect($taskIds)
            ->map(fn($id) => (int) $id);

        if ($existingTaskIds->diff($requestedTaskIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'tasks' => 'Devi fornire l’ordine completo delle task della colonna.',
            ]);
        }

        if ($tasks->count() !== count(array_unique($taskIds))) {
            throw ValidationException::withMessages([
                'tasks' => 'Una o più task non sono valide.',
            ]);
        }

        if (
            $tasks->contains(
                fn(Task $task) => $task->board_id !== $column->board_id
            )
        ) {
            throw ValidationException::withMessages([
                'tasks' => 'Una task appartiene a un’altra board.',
            ]);
        }

        DB::transaction(function () use ($column, $taskIds) {
            foreach ($taskIds as $index => $taskId) {
                Task::whereKey($taskId)->update([
                    'board_column_id' => $column->id,
                    'position' => ($index + 1) * 1000,
                ]);
            }
        });
    }
}
