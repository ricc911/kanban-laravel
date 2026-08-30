<?php

namespace App\Actions\Workspaces;

use App\Actions\Activity\LogActivity;
use App\Events\WorkspaceChanged;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateWorkspace
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $user, Workspace $workspace, string $name): Workspace
    {
        if (! $workspace->isOwner($user)) {
            throw ValidationException::withMessages(['workspace' => 'Solo il proprietario può modificare il workspace.']);
        }

        $name = trim($name);
        if ($workspace->name === $name) {
            return $workspace;
        }

        return DB::transaction(function () use ($user, $workspace, $name): Workspace {
            $oldName = $workspace->name;
            $workspace->update(['name' => $name]);
            $freshWorkspace = $workspace->fresh();
            $this->logger->execute($user, $freshWorkspace, 'workspace.updated', null, $freshWorkspace, [
                'workspace_name' => $freshWorkspace->name,
                'changes' => ['name' => ['old' => $oldName, 'new' => $freshWorkspace->name]],
            ]);
            WorkspaceChanged::dispatch('workspace.updated', (int) $freshWorkspace->id, [
                'workspace' => RealtimePayload::workspace($freshWorkspace),
            ]);

            return $freshWorkspace;
        });
    }
}
