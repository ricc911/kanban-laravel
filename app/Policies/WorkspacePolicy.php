<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->members()
            ->where('users.id', $user->id)
            ->exists();
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->type === 'shared'
            && $workspace->owner_id === $user->id;
    }

    public function invite(User $user, Workspace $workspace): bool
    {
        return $workspace->type === 'shared'
            && $workspace->owner_id === $user->id;
    }
}
