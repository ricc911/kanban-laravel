<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class TaskDeleted implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $taskId, public int $boardId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('board.'.$this->boardId)];
    }

    public function broadcastAs(): string
    {
        return 'task.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->taskId,
            'board_id' => $this->boardId,
        ];
    }
}
