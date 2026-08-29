<?php

namespace App\Actions\Workspaces;

use App\Actions\Activity\LogActivity;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Validation\ValidationException;

class RejectWorkspaceInvitation
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, WorkspaceInvitation $invitation): void
    {
        if (strtolower($invitation->email) !== strtolower($user->email)) {
            throw ValidationException::withMessages(['invitation' => 'Questo invito non è destinato a te.']);
        }

        $workspace = $invitation->workspace;
        $email = $invitation->email;
        $invitation->delete();
        $this->logger->execute($user, $workspace, 'workspace.invitation_rejected', null, null, ['email' => $email]);
    }
}
