<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ActivityLogged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param array<string, mixed> $activity */
    public function __construct(
        public int $workspaceId,
        public ?int $boardId,
        public array $activity,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('workspace.'.$this->workspaceId)];

        if ($this->boardId !== null) {
            $channels[] = new PrivateChannel('board.'.$this->boardId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'activity.logged';
    }

    /** @return array{activity: array<string, mixed>} */
    public function broadcastWith(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'board_id' => $this->boardId,
            'activity' => $this->activity,
        ];
    }
}
