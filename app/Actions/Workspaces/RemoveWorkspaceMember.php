<?php

namespace App\Actions\Workspaces;

use App\Actions\Activity\LogActivity;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Validation\ValidationException;

class RemoveWorkspaceMember
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $actor, Workspace $workspace, User $member): void
    {
        if (! $actor->can('update', $workspace) || $workspace->type !== 'shared') {
            throw ValidationException::withMessages(['workspace' => 'Non hai il permesso di gestire questo workspace.']);
        }
        if ($member->id === $workspace->owner_id) {
            throw ValidationException::withMessages(['member' => 'Il proprietario non può essere rimosso.']);
        }
        if (
            ! $workspace->members()->where('users.id', $member->id)->exists()
        ) {
            throw ValidationException::withMessages([
                'member' => 'Questo utente non fa parte del workspace.',
            ]);
        }
        $workspace->members()->detach($member->id);
        $this->logger->execute($actor, $workspace, 'workspace.member_removed', null, null, ['member_name' => $member->name]);
    }
}
