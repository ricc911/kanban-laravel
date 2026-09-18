<?php

namespace App\Services\Plans;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;

class PlanLimitService
{
    public function planForOwner(User $owner): Plan
    {
        return $owner->subscription()->with('plan')->firstOrFail()->plan;
    }

    public function ownedProjectCount(User $owner): int
    {
        return (int) $owner->ownedWorkspaces()->withCount('boards')->get()->sum('boards_count');
    }

    public function canCreateProject(Workspace $workspace): bool
    {
        $plan = $this->planForOwner($workspace->owner);

        return $plan->max_projects === null || $this->ownedProjectCount($workspace->owner) < $plan->max_projects;
    }

    public function ownedSharedWorkspaceCount(User $owner): int
    {
        return (int) $owner->ownedWorkspaces()->where('type', 'shared')->count();
    }

    public function canCreateSharedWorkspace(User $owner): bool
    {
        $limit = $this->planForOwner($owner)->max_shared_workspaces;

        return $limit === null || $this->ownedSharedWorkspaceCount($owner) < $limit;
    }

    public function memberCount(Workspace $workspace): int
    {
        return (int) $workspace->members()->count();
    }

    public function pendingInvitationCount(Workspace $workspace): int
    {
        return (int) $workspace->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->count();
    }

    public function canInviteMember(Workspace $workspace): bool
    {
        if ($workspace->type !== 'shared') {
            return false;
        }

        $limit = $this->planForOwner($workspace->owner)->max_members_per_workspace;
        $occupiedSlots = $this->memberCount($workspace) + $this->pendingInvitationCount($workspace);

        return $limit === null || $occupiedSlots < $limit;
    }

    public function canAcceptMember(Workspace $workspace): bool
    {
        if ($workspace->type !== 'shared') {
            return false;
        }

        $limit = $this->planForOwner($workspace->owner)->max_members_per_workspace;

        return $limit === null || $this->memberCount($workspace) < $limit;
    }
}
