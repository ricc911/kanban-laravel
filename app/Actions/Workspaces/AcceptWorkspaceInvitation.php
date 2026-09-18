<?php

namespace App\Actions\Workspaces;

use App\Actions\Activity\LogActivity;
use App\Events\UserRealtimeEvent;
use App\Events\WorkspaceChanged;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\Plans\PlanLimitService;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptWorkspaceInvitation
{
    public function __construct(private LogActivity $logger, private PlanLimitService $planLimits) {}

    public function execute(
        User $user,
        string $token
    ): Workspace {
        $invitation = WorkspaceInvitation::where('token', $token)
            ->with('workspace.owner.subscription.plan')
            ->firstOrFail();

        if ($invitation->accepted_at !== null) {
            throw ValidationException::withMessages([
                'invitation' => 'Questo invito è già stato utilizzato.',
            ]);
        }

        if ($invitation->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'invitation' => 'Questo invito è scaduto.',
            ]);
        }

        if (User::normalizeEmail($user->email) !== User::normalizeEmail($invitation->email)) {
            throw ValidationException::withMessages([
                'invitation' => 'Questo invito appartiene a un altro utente.',
            ]);
        }

        $workspace = $invitation->workspace;

        if ($workspace->type !== 'shared') {
            throw ValidationException::withMessages([
                'workspace' => Workspace::PERSONAL_SHARING_MESSAGE,
            ]);
        }

        DB::transaction(function () use ($workspace, $user, $invitation): void {
            $owner = User::query()->lockForUpdate()->findOrFail($workspace->owner_id);
            $workspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $workspace->setRelation('owner', $owner);

            if ($workspace->type !== 'shared') {
                throw ValidationException::withMessages([
                    'workspace' => Workspace::PERSONAL_SHARING_MESSAGE,
                ]);
            }

            if (! $this->planLimits->canAcceptMember($workspace)) {
                throw ValidationException::withMessages([
                    'workspace' => 'Il workspace ha raggiunto il limite di membri.',
                ]);
            }

            $workspace->members()->syncWithoutDetaching([
                $user->id => [
                    'role' => $invitation->role,
                    'joined_at' => now(),
                ],
            ]);

            $invitation->update([
                'accepted_at' => now(),
            ]);
            $this->logger->execute($user, $workspace, 'workspace.member_joined', null, $workspace, ['member_name' => $user->name]);
            WorkspaceChanged::dispatch('workspace.member_joined', (int) $workspace->id, [
                'member' => RealtimePayload::member($user, $invitation->role),
            ]);
            UserRealtimeEvent::dispatch('workspace.available', (int) $user->id, [
                'workspace' => RealtimePayload::workspace($workspace),
            ]);
            UserRealtimeEvent::dispatch('invitation.accepted', (int) $user->id, [
                'workspace_id' => (int) $workspace->id,
                'invitation_id' => (int) $invitation->id,
            ]);
            UserRealtimeEvent::dispatch('invitation.accepted', (int) $workspace->owner_id, [
                'workspace_id' => (int) $workspace->id,
                'invitation_id' => (int) $invitation->id,
            ]);
        });

        return $workspace;
    }
}
