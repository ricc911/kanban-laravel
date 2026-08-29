<?php

namespace App\Events;

use App\Events\Concerns\InteractsWithTaskPayload;
use App\Models\Task;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithTaskPayload, SerializesModels;

    public function __construct(Task $task)
    {
        $this->task = self::taskPayload($task);
    }

    /** @var array<string, bool|float|int|string|null> */
    public array $task;

    public function broadcastOn(): array
    {
        return [new PrivateChannel('board.'.$this->task['board_id'])];
    }

    public function broadcastAs(): string
    {
        return 'task.updated';
    }

    public function broadcastWith(): array
    {
        return ['task' => $this->task];
    }
}
