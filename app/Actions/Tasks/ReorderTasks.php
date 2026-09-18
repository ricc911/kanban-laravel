<?php

namespace App\Actions\Tasks;

use App\Events\TasksReordered;
use App\Models\BoardColumn;
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
        if (! $column->board->workspace->canEditContent($user)) {
            throw ValidationException::withMessages([
                'column' => 'Non hai accesso a questa colonna.',
            ]);
        }

        DB::transaction(function () use ($column, $taskIds): void {
            $existingTaskIds = $column->tasks()
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();
            $requestedTaskIds = collect($taskIds)
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($existingTaskIds !== $requestedTaskIds) {
                throw ValidationException::withMessages([
                    'tasks' => 'Devi fornire l’ordine completo delle task della colonna.',
                ]);
            }

            $positions = [];
            foreach ($taskIds as $index => $taskId) {
                $position = ($index + 1) * 1000;
                $column->tasks()->whereKey($taskId)->update(['position' => $position]);
                $positions[] = ['id' => (int) $taskId, 'position' => $position];
            }

            TasksReordered::dispatch((int) $column->board_id, (int) $column->id, $positions);
        });
    }
}
