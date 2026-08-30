<?php

namespace App\Actions\Tasks;

use App\Actions\Activity\LogActivity;
use App\Events\TaskCreated;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateTask
{
    public function __construct(private LogActivity $logger) {}

    public function execute(
        User $user,
        Board $board,
        BoardColumn $column,
        string $title,
        ?string $description = null,
        ?Category $category = null,
        ?string $priority = null,
        ?string $dueAt = null,
        ?string $color = null
    ): Task {
        if (! $board->workspace->canEditContent($user)) {
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

        $task = DB::transaction(function () use ($user, $board, $column, $category, $title, $description, $priority, $dueAt, $color): Task {
            $position = (($column->tasks()->max('position') ?? 0) + 1000);

            $task = Task::create([
                'board_id' => $board->id,
                'board_column_id' => $column->id,
                'category_id' => $category?->id,
                'title' => trim($title),
                'color' => $color ?? '#2563eb',
                'description' => $description,
                'priority' => $priority,
                'due_at' => $dueAt,
                'position' => $position,
            ]);
            $task->load('assignees');
            $this->logger->execute($user, $board->workspace, 'task.created', $board, $task, ['task_title' => $task->title]);

            return $task;
        });

        TaskCreated::dispatch($task);

        return $task;
    }
}
