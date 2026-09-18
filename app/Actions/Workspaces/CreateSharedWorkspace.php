<?php

namespace App\Actions\Workspaces;

use App\Actions\Activity\LogActivity;
use App\Events\UserRealtimeEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Plans\PlanLimitService;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSharedWorkspace
{
    public function __construct(private LogActivity $logger, private PlanLimitService $planLimits) {}

    public function execute(User $user, string $name): Workspace
    {
        return DB::transaction(function () use ($user, $name) {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);

            if (! $this->planLimits->canCreateSharedWorkspace($owner)) {
                $limit = $this->planLimits->planForOwner($owner)->max_shared_workspaces;

                throw ValidationException::withMessages([
                    'workspace' => $limit === 0
                        ? 'Il tuo piano non consente di creare altri workspace condivisi.'
                        : 'Hai raggiunto il limite di workspace condivisi del tuo piano.',
                ]);
            }

            $workspace = Workspace::create([
                'owner_id' => $owner->id,
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
