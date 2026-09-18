<?php

namespace App\Actions\Tasks;

use App\Actions\Activity\LogActivity;
use App\Events\TaskUpdated;
use App\Models\Category;
use App\Models\Task;
use App\Models\TaskReminderDelivery;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateTask
{
    public function __construct(private LogActivity $logger) {}

    /** @param array<int, string>|null $providedFields */
    public function execute(
        User $user,
        Task $task,
        string $title,
        ?string $description = null,
        ?string $priority = null,
        ?string $dueAt = null,
        ?Category $category = null,
        ?string $color = null,
        ?array $providedFields = null
    ): Task {
        $providedFields ??= ['title', 'description', 'priority', 'due_at', 'category_id', 'color'];
        $provided = array_fill_keys($providedFields, true);

        if (! $task->board->workspace->canEditContent($user)) {
            throw ValidationException::withMessages([
                'task' => 'Non hai accesso a questa task.',
            ]);
        }

        if (
            isset($provided['category_id']) &&
            $category !== null &&
            $category->board_id !== $task->board_id
        ) {
            throw ValidationException::withMessages([
                'category' => 'La categoria appartiene a un’altra board.',
            ]);
        }

        $board = $task->board;
        $oldCategory = isset($provided['category_id']) ? $task->category : null;
        $oldDueAt = $task->due_at;
        $newColor = $color;
        $newDueAt = $dueAt !== null ? Carbon::parse($dueAt) : null;
        $changes = [];
        foreach (['title' => [$task->title, trim($title)], 'description' => [$task->description, $description], 'priority' => [$task->priority, $priority]] as $field => [$old, $new]) {
            if (isset($provided[$field]) && $old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }
        if (
            isset($provided['due_at']) &&
            (($oldDueAt === null) !== ($newDueAt === null) ||
                ($oldDueAt !== null && $newDueAt !== null && ! $oldDueAt->equalTo($newDueAt)))
        ) {
            $changes['due_at'] = [
                'old' => $oldDueAt?->toISOString(),
                'new' => $newDueAt?->toISOString(),
            ];
        }
        $oldCategoryValue = $oldCategory ? ['id' => $oldCategory->id, 'name' => $oldCategory->name] : null;
        $newCategoryValue = $category ? ['id' => $category->id, 'name' => $category->name] : null;
        if (isset($provided['category_id']) && $oldCategoryValue !== $newCategoryValue) {
            $changes['category'] = ['old' => $oldCategoryValue, 'new' => $newCategoryValue];
        }

        if (isset($provided['color']) && $task->color !== $newColor) {
            $changes['color'] = ['old' => $task->color, 'new' => $newColor];
        }

        $updates = array_intersect_key([
            'title' => trim($title),
            'color' => $newColor,
            'description' => $description,
            'priority' => $priority,
            'due_at' => $newDueAt,
            'category_id' => $category?->id,
        ], $provided);

        return DB::transaction(function () use ($user, $task, $board, $updates, $changes): Task {
            $task->update($updates);
            if (array_key_exists('due_at', $changes)) {
                TaskReminderDelivery::query()->where('task_id', $task->id)->delete();
            }
            $freshTask = $task->fresh();
            if ($changes) {
                $this->logger->execute($user, $board->workspace, 'task.updated', $board, $freshTask, ['task_title' => $freshTask->title, 'changes' => $changes]);
                TaskUpdated::dispatch($freshTask);
            }

            return $freshTask;
        });
    }
}
