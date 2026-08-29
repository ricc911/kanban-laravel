<?php

namespace App\Events\Concerns;

use App\Models\Task;

trait InteractsWithTaskPayload
{
    /**
     * @return array<string, bool|float|int|string|null>
     */
    private static function taskPayload(Task $task): array
    {
        return [
            'id' => (int) $task->id,
            'board_id' => (int) $task->board_id,
            'board_column_id' => (int) $task->board_column_id,
            'category_id' => $task->category_id === null ? null : (int) $task->category_id,
            'title' => $task->title,
            'description' => $task->description,
            'position' => (int) $task->position,
            'priority' => $task->priority,
            'due_at' => $task->due_at?->toISOString(),
            'archived' => (bool) $task->archived,
        ];
    }
}
