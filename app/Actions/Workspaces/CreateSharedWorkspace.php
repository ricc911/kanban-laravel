<?php

namespace App\Actions\Workspaces;

use App\Actions\Activity\LogActivity;
use App\Events\UserRealtimeEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;

class CreateSharedWorkspace
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, string $name): Workspace
    {
        return DB::transaction(function () use ($user, $name) {
            $workspace = Workspace::create([
                'owner_id' => $user->id,
                'name' => $name,
                'type' => 'shared',
            ]);

            $workspace->members()->attach($user->id, [
                'role' => 'owner',
                'joined_at' => now(),
            ]);
            $this->logger->execute($user, $workspace, 'workspace.created', null, $workspace, ['workspace_name' => $workspace->name]);
            UserRealtimeEvent::dispatch('workspace.created', (int) $user->id, [
                'workspace' => RealtimePayload::workspace($workspace),
            ]);

            return $workspace;
        });
    }
}
