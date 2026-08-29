<?php

namespace App\Actions\Workspaces;

use App\Actions\Activity\LogActivity;
use App\Events\UserRealtimeEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Support\RealtimePayload;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteWorkspaceMember
{
    public function __construct(private LogActivity $logger) {}

    public function execute(
        User $actor,
        Workspace $workspace,
        string $email,
        string $role = 'member'
    ): WorkspaceInvitation {
        if (! $workspace->canManageMembers($actor)) {
            throw ValidationException::withMessages([
                'workspace' => 'Non hai il permesso di invitare membri.',
            ]);
        }

        if ($workspace->roleFor($actor) === 'admin' && $role === 'admin') {
            throw ValidationException::withMessages(['role' => 'Un amministratore può invitare solo membri o visualizzatori.']);
        }

        $workspace->loadMissing('owner.subscription.plan');

        $plan = $workspace->owner->subscription->plan;

        $membersCount = $workspace->members()->count();

        $pendingInvitationsCount = $workspace->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->count();

        $occupiedSlots = $membersCount + $pendingInvitationsCount;

        if (
            $plan->max_members_per_workspace !== null &&
            $occupiedSlots >= $plan->max_members_per_workspace
        ) {
            throw ValidationException::withMessages([
                'workspace' => 'Hai raggiunto il limite di membri del workspace.',
            ]);
        }

        $email = strtolower(trim($email));

        if (
            $workspace->members()
                ->where('email', $email)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'email' => 'Questo utente fa già parte del workspace.',
            ]);
        }

        $invitation = WorkspaceInvitation::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'email' => $email,
            ],
            [
                'role' => $role,
                'token' => Str::random(64),
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
            ]
        );
        $this->logger->execute($actor, $workspace, 'workspace.member_invited', null, $invitation, ['email' => $email]);

        $invitation->loadMissing(['workspace.owner:id,name', 'workspace:id,name,owner_id,type']);
        $recipient = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($recipient !== null) {
            UserRealtimeEvent::dispatch('invitation.created', (int) $recipient->id, [
                'invitation' => RealtimePayload::invitation($invitation),
            ]);
        }

        UserRealtimeEvent::dispatch('invitation.pending.created', (int) $actor->id, [
            'workspace_id' => (int) $workspace->id,
            'invitation_id' => (int) $invitation->id,
        ]);

        return $invitation;
    }
}
