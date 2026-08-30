<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Support\NotificationPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskCommentCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public Task $task, public TaskComment $comment, public User $actor) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [...NotificationPayload::taskContext($this->task), 'type' => 'task_comment_created', 'comment_id' => (int) $this->comment->id, 'comment_preview' => NotificationPayload::commentPreview($this->comment->body), 'actor' => NotificationPayload::actor($this->actor)];
    }
}
