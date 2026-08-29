<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class BoardChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $action,
        public int $workspaceId,
        public int $boardId,
        public array $payload = [],
        public bool $broadcastToWorkspace = false,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('board.'.$this->boardId)];

        if ($this->broadcastToWorkspace) {
            $channels[] = new PrivateChannel('workspace.'.$this->workspaceId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return $this->action;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'board_id' => $this->boardId,
            ...$this->payload,
        ];
    }
}
