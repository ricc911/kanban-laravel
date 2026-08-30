<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskAssigneesChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /** @param array<int, array<string, int|string|null>> $assignees */
    public function __construct(public Task $task, public array $assignees) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('board.'.$this->task->board_id)];
    }

    public function broadcastAs(): string
    {
        return 'task.assignees_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'board_id' => (int) $this->task->board_id,
            'task_id' => (int) $this->task->id,
            'assignees' => $this->assignees,
        ];
    }
}
