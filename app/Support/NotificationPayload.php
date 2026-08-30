<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class NotificationPayload
{
    public static function actor(User $user): array
    {
        return RealtimePayload::assignee($user);
    }

    public static function taskContext(Task $task): array
    {
        return ['workspace_id' => (int) $task->board->workspace_id, 'board_id' => (int) $task->board_id, 'task_id' => (int) $task->id, 'task_title' => $task->title];
    }

    public static function commentPreview(string $body): string
    {
        return Str::limit(preg_replace('/\s+/u', ' ', trim($body)) ?? '', 140);
    }

    public static function database(DatabaseNotification $notification): array
    {
        return ['id' => $notification->id, 'type' => $notification->data['type'] ?? $notification->type, 'data' => $notification->data, 'read_at' => $notification->read_at?->toISOString(), 'created_at' => $notification->created_at?->toISOString()];
    }
}
