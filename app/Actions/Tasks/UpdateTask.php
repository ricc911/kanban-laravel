<?php

namespace App\Actions\Tasks;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateTask
{
    public function execute(
        User $user,
        Task $task,
        string $title,
        ?string $description = null,
        ?string $priority = null,
        ?string $dueAt = null,
        ?Category $category = null
    ): Task {
        if (! $task->board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'task' => 'Non hai accesso a questa task.',
            ]);
        }

        if (
            $category !== null &&
            $category->board_id !== $task->board_id
        ) {
            throw ValidationException::withMessages([
                'category' => 'La categoria appartiene a un’altra board.',
            ]);
        }

        $task->update([
            'title' => trim($title),
            'description' => $description,
            'priority' => $priority,
            'due_at' => $dueAt,
            'category_id' => $category?->id,
        ]);

        return $task->fresh();
    }
}
