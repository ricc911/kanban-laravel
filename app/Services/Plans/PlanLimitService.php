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

    public function sharedWorkspaceCount(User $owner): int
    {
        return $this->sharedWorkspaceUsage($owner)['actual'];
    }

    public function reservedSharedWorkspaceCount(User $owner): int
    {
        return $this->sharedWorkspaceUsage($owner)['reserved'];
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
        $plan = $this->planForOwner($workspace->owner);
        $occupiedSlots = $this->memberCount($workspace) + $this->pendingInvitationCount($workspace);

        return ($plan->max_members_per_workspace === null || $occupiedSlots < $plan->max_members_per_workspace)
            && $this->canShareWorkspace($workspace);
    }

    public function canAcceptMember(Workspace $workspace): bool
    {
        $plan = $this->planForOwner($workspace->owner);

        return ($plan->max_members_per_workspace === null || $this->memberCount($workspace) < $plan->max_members_per_workspace)
            && $this->canShareWorkspace($workspace);
    }

    public function canShareWorkspace(Workspace $workspace): bool
    {
        if ($this->memberCount($workspace) > 1) {
            return true;
        }

        $plan = $this->planForOwner($workspace->owner);
        if ($plan->max_shared_workspaces === null) {
            return true;
        }

        $usage = $this->sharedWorkspaceUsage($workspace->owner);
        $currentWorkspaceReserved = $this->workspaceHasActiveInvitation($workspace);
        $reservedWithoutCurrent = $usage['reserved'] - ($currentWorkspaceReserved ? 1 : 0);

        return $plan->max_shared_workspaces > $usage['actual'] + $reservedWithoutCurrent;
    }

    /**
     * @return array{actual: int, reserved: int}
     */
    private function sharedWorkspaceUsage(User $owner): array
    {
        $workspaces = $owner->ownedWorkspaces()->withCount([
            'members',
            'invitations as active_invitations_count' => fn ($query) => $query
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now()),
        ])->get();

        return [
            'actual' => $workspaces->where('members_count', '>', 1)->count(),
            'reserved' => $workspaces
                ->where('members_count', 1)
                ->where('active_invitations_count', '>', 0)
                ->count(),
        ];
    }

    private function workspaceHasActiveInvitation(Workspace $workspace): bool
    {
        return $workspace->invitations()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->exists();
    }
}
