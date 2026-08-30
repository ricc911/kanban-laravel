<?php

namespace App\Actions\Workspaces;

use App\Actions\Activity\LogActivity;
use App\Events\UserRealtimeEvent;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RejectWorkspaceInvitation
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, WorkspaceInvitation $invitation): void
    {
        $isRecipient = strtolower($invitation->email) === strtolower($user->email);
        $isManager = $invitation->workspace->canManageMembers($user);

        if (! $isRecipient && ! $isManager) {
            throw ValidationException::withMessages(['invitation' => 'Questo invito non è destinato a te.']);
        }

        DB::transaction(function () use ($user, $invitation, $isRecipient): void {
            $workspace = $invitation->workspace;
            $ownerId = (int) $workspace->owner_id;
            $invitationId = (int) $invitation->id;
            $email = $invitation->email;
            $invitation->delete();
            if ($isRecipient) {
                $this->logger->execute($user, $workspace, 'workspace.invitation_rejected', null, null, ['email' => $email]);
                UserRealtimeEvent::dispatch('invitation.rejected', $ownerId, [
                    'workspace_id' => (int) $workspace->id,
                    'invitation_id' => $invitationId,
                ]);
            }

            $managerIds = $workspace->members()
                ->wherePivotIn('role', ['owner', 'admin'])
                ->pluck('users.id');

            foreach ($managerIds as $managerId) {
                UserRealtimeEvent::dispatch('invitation.pending.removed', (int) $managerId, [
                    'workspace_id' => (int) $workspace->id,
                    'invitation_id' => $invitationId,
                ]);
            }
        });
    }
}
