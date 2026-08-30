<?php

namespace App\Events;

use App\Models\TaskComment;
use App\Support\RealtimePayload;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskCommentCreated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public TaskComment $comment, public int $boardId, public int $commentsCount) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('board.'.$this->boardId)];
    }

    public function broadcastAs(): string
    {
        return 'task.comment_created';
    }

    public function broadcastWith(): array
    {
        return ['board_id' => $this->boardId, 'task_id' => (int) $this->comment->task_id, 'comment' => RealtimePayload::comment($this->comment), 'comments_count' => $this->commentsCount];
    }
}
