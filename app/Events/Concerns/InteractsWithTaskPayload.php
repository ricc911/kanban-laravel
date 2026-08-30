<?php

namespace App\Events\Concerns;

use App\Models\Task;
use App\Support\RealtimePayload;

trait InteractsWithTaskPayload
{
    /**
     * @return array<string, bool|float|int|string|null|array<int, array<string, int|string|null>>>
     */
    private static function taskPayload(Task $task): array
    {
        $payload = [
            'id' => (int) $task->id,
            'board_id' => (int) $task->board_id,
            'board_column_id' => (int) $task->board_column_id,
            'category_id' => $task->category_id === null ? null : (int) $task->category_id,
            'title' => $task->title,
            'color' => $task->color,
            'description' => $task->description,
            'position' => (int) $task->position,
            'priority' => $task->priority,
            'due_at' => $task->due_at?->toISOString(),
            'archived' => (bool) $task->archived,
            'comments_count' => (int) ($task->comments_count ?? 0),
        ];

        if ($task->relationLoaded('assignees')) {
            $payload['assignees'] = RealtimePayload::assignees($task->assignees);
        }

        return $payload;
    }
}
