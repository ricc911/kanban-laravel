<?php

namespace App\Actions\Tasks;

use App\Actions\Activity\LogActivity;
use App\Events\TaskUpdated;
use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateTask
{
    public function __construct(private LogActivity $logger) {}

    public function execute(
        User $user,
        Task $task,
        string $title,
        ?string $description = null,
        ?string $priority = null,
        ?string $dueAt = null,
        ?Category $category = null,
        ?string $color = null
    ): Task {
        if (! $task->board->workspace->canEditContent($user)) {
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

        $board = $task->board;
        $oldCategory = $task->category;
        $oldDueAt = $task->due_at;
        $newColor = $color ?? $task->color;
        $newDueAt = $dueAt !== null ? Carbon::parse($dueAt) : null;
        $changes = [];
        foreach (['title' => [$task->title, trim($title)], 'description' => [$task->description, $description], 'priority' => [$task->priority, $priority]] as $field => [$old, $new]) {
            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }
        if (
            ($oldDueAt === null) !== ($newDueAt === null) ||
            ($oldDueAt !== null && $newDueAt !== null && ! $oldDueAt->equalTo($newDueAt))
        ) {
            $changes['due_at'] = [
                'old' => $oldDueAt?->toISOString(),
                'new' => $newDueAt?->toISOString(),
            ];
        }
        $oldCategoryValue = $oldCategory ? ['id' => $oldCategory->id, 'name' => $oldCategory->name] : null;
        $newCategoryValue = $category ? ['id' => $category->id, 'name' => $category->name] : null;
        if ($oldCategoryValue !== $newCategoryValue) {
            $changes['category'] = ['old' => $oldCategoryValue, 'new' => $newCategoryValue];
        }

        if ($task->color !== $newColor) {
            $changes['color'] = ['old' => $task->color, 'new' => $newColor];
        }

        return DB::transaction(function () use ($user, $task, $board, $title, $description, $priority, $newDueAt, $category, $newColor, $changes): Task {
            $task->update([
                'title' => trim($title),
                'color' => $newColor,
                'description' => $description,
                'priority' => $priority,
                'due_at' => $newDueAt,
                'category_id' => $category?->id,
            ]);
            $freshTask = $task->fresh();
            if ($changes) {
                $this->logger->execute($user, $board->workspace, 'task.updated', $board, $freshTask, ['task_title' => $freshTask->title, 'changes' => $changes]);
                TaskUpdated::dispatch($freshTask);
            }

            return $freshTask;
        });
    }
}
