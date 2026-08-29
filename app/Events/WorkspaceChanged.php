<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class WorkspaceChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $action,
        public int $workspaceId,
        public array $payload = [],
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('workspace.'.$this->workspaceId)];
    }

    public function broadcastAs(): string
    {
        return $this->action;
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['workspace_id' => $this->workspaceId, ...$this->payload];
    }
}
