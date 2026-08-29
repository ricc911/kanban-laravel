<?php

namespace App\Actions\Workspaces;

use App\Actions\Activity\LogActivity;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSharedWorkspace
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, string $name): Workspace
    {
        $subscription = $user->subscription()
            ->with('plan')
            ->firstOrFail();

        $plan = $subscription->plan;

        $currentSharedWorkspaces = $user->ownedWorkspaces()
            ->where('type', 'shared')
            ->count();

        if (
            $plan->max_shared_workspaces !== null &&
            $currentSharedWorkspaces >= $plan->max_shared_workspaces
        ) {
            throw ValidationException::withMessages([
                'workspace' => 'Hai raggiunto il limite di workspace condivisi del tuo piano.',
            ]);
        }

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

            return $workspace;
        });
    }
}
