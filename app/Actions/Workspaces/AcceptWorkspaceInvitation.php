<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptWorkspaceInvitation
{
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

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            throw ValidationException::withMessages([
                'invitation' => 'Questo invito appartiene a un altro utente.',
            ]);
        }

        $workspace = $invitation->workspace;
        $plan = $workspace->owner->subscription->plan;

        if (
            $plan->max_members_per_workspace !== null &&
            $workspace->members()->count() >= $plan->max_members_per_workspace
        ) {
            throw ValidationException::withMessages([
                'workspace' => 'Il workspace ha raggiunto il limite di membri.',
            ]);
        }

        DB::transaction(function () use ($workspace, $user, $invitation) {
            $workspace->members()->syncWithoutDetaching([
                $user->id => [
                    'role' => $invitation->role,
                    'joined_at' => now(),
                ],
            ]);

            $invitation->update([
                'accepted_at' => now(),
            ]);
        });

        return $workspace;
    }
}
