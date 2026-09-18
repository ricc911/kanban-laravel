<?php

namespace App\Actions\Workspaces;

use App\Actions\Activity\LogActivity;
use App\Events\UserRealtimeEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\Plans\PlanLimitService;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteWorkspaceMember
{
    public function __construct(private LogActivity $logger, private PlanLimitService $planLimits) {}

    public function execute(
        User $actor,
        Workspace $workspace,
        string $email,
        string $role = 'member'
    ): WorkspaceInvitation {
        if ($workspace->type !== 'shared') {
            throw ValidationException::withMessages([
                'workspace' => Workspace::PERSONAL_SHARING_MESSAGE,
            ]);
        }

        if (! $workspace->canManageMembers($actor)) {
            throw ValidationException::withMessages([
                'workspace' => 'Non hai il permesso di invitare membri.',
            ]);
        }

        if ($workspace->roleFor($actor) === 'admin' && $role === 'admin') {
            throw ValidationException::withMessages(['role' => 'Un amministratore puÃ² invitare solo membri o visualizzatori.']);
        }

        [$workspace, $invitation, $recipient] = DB::transaction(function () use ($actor, $workspace, $email, $role): array {
            $owner = User::query()->lockForUpdate()->findOrFail($workspace->owner_id);
            $workspace = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
            $workspace->setRelation('owner', $owner);

            if ($workspace->type !== 'shared') {
                throw ValidationException::withMessages([
                    'workspace' => Workspace::PERSONAL_SHARING_MESSAGE,
                ]);
            }

            if (! $this->planLimits->canInviteMember($workspace)) {
                throw ValidationException::withMessages([
                    'workspace' => 'Hai raggiunto il limite di membri del workspace.',
                ]);
            }

            $identifier = trim($email);
            $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
            $recipient = $isEmail
                ? User::query()->where('email', User::normalizeEmail($identifier))->first()
                : User::query()->where('username', User::normalizeUsername($identifier))->first();

            if (! $isEmail && $recipient === null) {
                throw ValidationException::withMessages(['email' => 'Username non trovato.']);
            }

            $email = $recipient?->email ?? User::normalizeEmail($identifier);

            if ($recipient?->is($actor)) {
                throw ValidationException::withMessages(['email' => 'Non puoi invitare te stesso.']);
            }

            if ($workspace->members()->whereRaw('LOWER(users.email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Questo utente fa giÃ  parte del workspace.',
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

            return [$workspace, $invitation, $recipient];
        });

        $this->logger->execute($actor, $workspace, 'workspace.member_invited', null, $invitation, ['email' => $email]);

        $invitation->loadMissing(['workspace.owner:id,name', 'workspace:id,name,owner_id,type']);
        $recipient ??= User::query()->where('email', $email)->first();

        if ($recipient !== null) {
            UserRealtimeEvent::dispatch('invitation.created', (int) $recipient->id, [
                'invitation' => RealtimePayload::invitation($invitation),
            ]);
        }

        $managerIds = $workspace->members()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->pluck('users.id');

        foreach ($managerIds as $managerId) {
            UserRealtimeEvent::dispatch('invitation.pending.created', (int) $managerId, [
                'workspace_id' => (int) $workspace->id,
                'invitation_id' => (int) $invitation->id,
            ]);
        }

        return $invitation;
    }
}
