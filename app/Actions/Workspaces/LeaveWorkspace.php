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

class LeaveWorkspace
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, Workspace $workspace): void
    {
        if ($workspace->type !== 'shared') {
            throw ValidationException::withMessages(['workspace' => 'Il workspace personale non può essere lasciato.']);
        }
        if ($workspace->owner_id === $user->id) {
            throw ValidationException::withMessages(['workspace' => 'Il proprietario non può lasciare il workspace.']);
        }
        if (! $workspace->hasMember($user)) {
            throw ValidationException::withMessages(['workspace' => 'Non fai parte di questo workspace.']);
        }
        DB::transaction(function () use ($user, $workspace): void {
            $this->logger->execute($user, $workspace, 'workspace.member_left', null, null, ['member_name' => $user->name]);
            $workspace->members()->detach($user->id);
            WorkspaceChanged::dispatch('workspace.member_left', (int) $workspace->id, [
                'member' => RealtimePayload::member($user, 'member'),
            ]);
            UserRealtimeEvent::dispatch('workspace.access_removed', (int) $user->id, [
                'workspace' => RealtimePayload::workspace($workspace),
            ]);
        });
    }
}
