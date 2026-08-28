<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteWorkspaceMember
{
    public function execute(
        User $actor,
        Workspace $workspace,
        string $email
    ): WorkspaceInvitation {
        if (! $actor->can('invite', $workspace)) {
            throw ValidationException::withMessages([
                'workspace' => 'Non hai il permesso di invitare membri.',
            ]);
        }

        $workspace->loadMissing('owner.subscription.plan');

        $plan = $workspace->owner->subscription->plan;

        $membersCount = $workspace->members()->count();

        if (
            $plan->max_members_per_workspace !== null &&
            $membersCount >= $plan->max_members_per_workspace
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

        return WorkspaceInvitation::updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'email' => $email,
            ],
            [
                'role' => 'member',
                'token' => Str::random(64),
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
            ]
        );
    }
}
