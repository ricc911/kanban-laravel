<?php

namespace App\Actions\Tasks;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateTask
{
    public function execute(
        User $user,
        Board $board,
        BoardColumn $column,
        string $title,
        ?string $description = null,
        ?Category $category = null,
        ?string $priority = null,
        ?string $dueAt = null
    ): Task {
        if (! $board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'board' => 'Non hai accesso a questa board.',
            ]);
        }

        if ($column->board_id !== $board->id) {
            throw ValidationException::withMessages([
                'column' => 'La colonna appartiene a un’altra board.',
            ]);
        }

        if (
            $category !== null &&
            $category->board_id !== $board->id
        ) {
            throw ValidationException::withMessages([
                'category' => 'La categoria appartiene a un’altra board.',
            ]);
        }

        $position = (($column->tasks()->max('position') ?? 0) + 1000);

        return Task::create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'category_id' => $category?->id,
            'title' => trim($title),
            'description' => $description,
            'priority' => $priority,
            'due_at' => $dueAt,
            'position' => $position,
        ]);
    }
}
