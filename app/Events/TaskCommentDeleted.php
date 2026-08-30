<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCommentDeleted implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $commentId, public int $taskId, public int $boardId, public int $commentsCount) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('board.'.$this->boardId)];
    }

    public function broadcastAs(): string
    {
        return 'task.comment_deleted';
    }

    public function broadcastWith(): array
    {
        return ['board_id' => $this->boardId, 'task_id' => $this->taskId, 'comment_id' => $this->commentId, 'comments_count' => $this->commentsCount];
    }
}
