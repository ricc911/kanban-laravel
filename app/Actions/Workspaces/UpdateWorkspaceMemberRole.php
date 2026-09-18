<?php

namespace App\Actions\Workspaces;

use App\Actions\Activity\LogActivity;
use App\Events\UserRealtimeEvent;
use App\Events\WorkspaceChanged;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateWorkspaceMemberRole
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $actor, Workspace $workspace, User $member, string $role): void
    {
        if ($workspace->type !== 'shared') {
            throw ValidationException::withMessages(['workspace' => Workspace::PERSONAL_SHARING_MESSAGE]);
        }

        if (! $workspace->canManageMember($actor, $member)) {
            throw ValidationException::withMessages(['member' => 'Non hai il permesso di modificare questo ruolo.']);
        }
        if ($actor->id !== $workspace->owner_id && $role === 'admin') {
            throw ValidationException::withMessages(['role' => 'Solo il proprietario può assegnare il ruolo amministratore.']);
        }

        $oldRole = $workspace->roleFor($member);
        DB::transaction(function () use ($actor, $workspace, $member, $role, $oldRole): void {
            $workspace->members()->updateExistingPivot($member->id, ['role' => $role]);
            $this->logger->execute($actor, $workspace, 'workspace.member_role_updated', null, null, [
                'member_id' => $member->id,
                'member_name' => $member->name,
                'old_role' => $oldRole,
                'new_role' => $role,
            ]);
            WorkspaceChanged::dispatch('workspace.member_role_updated', (int) $workspace->id, [
                'member' => RealtimePayload::member($member, $role),
            ]);
            UserRealtimeEvent::dispatch('workspace.role_updated', (int) $member->id, [
                'workspace' => RealtimePayload::workspace($workspace),
                'role' => $role,
            ]);
        });
    }
}
