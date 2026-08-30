<?php

namespace App\Actions\Tasks;

use App\Events\UserRealtimeEvent;
use App\Models\Task;
use App\Models\TaskReminderDelivery;
use App\Notifications\TaskDueSoonNotification;
use App\Notifications\TaskOverdueNotification;
use App\Support\NotificationPayload;
use Illuminate\Support\Facades\DB;

class SendTaskDueReminders
{
    public function execute(): array
    {
        $now = now();
        $counts = ['due_soon' => 0, 'overdue' => 0];
        Task::query()->where('archived', false)->whereNotNull('due_at')->where('due_at', '<=', $now->copy()->addDay())->with('board.workspace', 'assignees')->chunkById(100, function ($tasks) use (&$counts): void {
            foreach ($tasks as $task) {
                $kind = $task->due_at->isPast() ? 'overdue' : 'due_soon';
                foreach ($task->assignees as $user) {
                    $created = false;
                    DB::transaction(function () use ($task, $user, $kind, &$created): void {
                        $created = TaskReminderDelivery::query()->insertOrIgnore(['task_id' => $task->id, 'user_id' => $user->id, 'kind' => $kind, 'due_at' => $task->due_at, 'created_at' => now(), 'updated_at' => now()]) === 1;
                        if ($created) {
                            $user->notify($kind === 'overdue' ? new TaskOverdueNotification($task) : new TaskDueSoonNotification($task));
                        }
                    });
                    if ($created) {
                        $counts[$kind]++;
                        $notification = $user->notifications()->latest()->first();
                        if ($notification) {
                            UserRealtimeEvent::dispatch('notification.created', (int) $user->id, ['notification' => NotificationPayload::database($notification)]);
                        }
                    }
                }
            }
        });

        return $counts;
    }
}
