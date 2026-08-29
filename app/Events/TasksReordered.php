<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class TasksReordered implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  array<int, array{id: int, position: int}>  tasks
     */
    public function __construct(
        public int $boardId,
        public int $columnId,
        public array $tasks,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('board.'.$this->boardId)];
    }

    public function broadcastAs(): string
    {
        return 'tasks.reordered';
    }

    public function broadcastWith(): array
    {
        return [
            'board_id' => $this->boardId,
            'column_id' => $this->columnId,
            'tasks' => $this->tasks,
        ];
    }
}
