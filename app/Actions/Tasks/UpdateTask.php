<?php

namespace App\Actions\Tasks;

use App\Actions\Activity\LogActivity;
use App\Models\Category;
use App\Models\Task;
use App\Models\User;
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

        $oldCategory = $task->category;
        $oldDueAt = $task->due_at?->toISOString();
        $newDueAt = $dueAt ? date(DATE_ATOM, strtotime($dueAt)) : null;
        $changes = [];
        foreach (['title' => [$task->title, trim($title)], 'description' => [$task->description, $description], 'priority' => [$task->priority, $priority], 'due_at' => [$oldDueAt, $newDueAt]] as $field => [$old, $new]) {
            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }
        $oldCategoryValue = $oldCategory ? ['id' => $oldCategory->id, 'name' => $oldCategory->name] : null;
        $newCategoryValue = $category ? ['id' => $category->id, 'name' => $category->name] : null;
        if ($oldCategoryValue != $newCategoryValue) {
            $changes['category'] = ['old' => $oldCategoryValue, 'new' => $newCategoryValue];
        }

        $task->update([
            'title' => trim($title),
            'description' => $description,
            'priority' => $priority,
            'due_at' => $dueAt,
            'category_id' => $category?->id,
        ]);
        if ($changes) {
            $this->logger->execute($user, $task->board->workspace, 'task.updated', $task->board, $task, ['task_title' => $task->title, 'changes' => $changes]);
        }

        return $task->fresh();
    }
}
