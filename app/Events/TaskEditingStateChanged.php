<?php

namespace App\Events;

use App\Models\Task;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskEditingStateChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $user,
        public bool $active,
        public string $sessionId,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('board.'.$this->task->board_id)];
    }

    public function broadcastAs(): string
    {
        return 'task.editing_state_changed';
    }

    public function broadcastWith(): array
    {
        return [
            'board_id' => (int) $this->task->board_id,
            'task_id' => (int) $this->task->id,
            'active' => $this->active,
            'session_id' => $this->sessionId,
            'user' => [
                'id' => (int) $this->user->id,
                'name' => $this->user->name,
                'last_name' => $this->user->last_name,
                'username' => $this->user->username,
            ],
        ];
    }
}
