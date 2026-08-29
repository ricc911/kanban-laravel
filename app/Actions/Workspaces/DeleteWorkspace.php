<?php

namespace App\Actions\Workspaces;

use App\Events\UserRealtimeEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RealtimePayload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class DeleteWorkspace
{
    public function execute(User $user, Workspace $workspace): void
    {
        if ($workspace->type !== 'shared' || ! $workspace->isOwner($user)) {
            throw new AuthorizationException('Solo il proprietario può eliminare un workspace condiviso.');
        }

        $memberIds = $workspace->members()->pluck('users.id')->map(static fn ($id): int => (int) $id)->all();

        DB::transaction(function () use ($workspace, $memberIds): void {
            $workspace->delete();

            foreach ($memberIds as $memberId) {
                UserRealtimeEvent::dispatch('workspace.deleted', $memberId, [
                    'workspace' => RealtimePayload::workspace($workspace),
                ]);
            }
        });
    }
}
