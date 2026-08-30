<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Support\NotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public Task $task, public User $actor) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [...NotificationPayload::taskContext($this->task), 'type' => 'task_assigned', 'actor' => NotificationPayload::actor($this->actor)];
    }
}
