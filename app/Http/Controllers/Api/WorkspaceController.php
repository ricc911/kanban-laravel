<?php

namespace App\Http\Controllers\Api;

use App\Actions\Workspaces\CreateSharedWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Actions\Workspaces\LeaveWorkspace;
use App\Actions\Workspaces\RemoveWorkspaceMember;
use App\Actions\Workspaces\UpdateWorkspaceMemberRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeaveWorkspaceRequest;
use App\Http\Requests\RemoveWorkspaceMemberRequest;
use App\Http\Requests\StoreSharedWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceMemberRoleRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkspaceController extends Controller
{
    public function destroy(Request $request, Workspace $workspace, DeleteWorkspace $action): JsonResponse
    {
        $action->execute($request->user(), $workspace);

        return response()->json(status: 204);
    }

    public function store(StoreSharedWorkspaceRequest $request, CreateSharedWorkspace $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $request->validated('name'))], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $workspaces = $request->user()
            ->workspaces()
            ->withPivot(['role', 'joined_at'])
            ->with('owner:id,name,email')
            ->orderBy('name')
            ->get();

        $workspaces->each(function (Workspace $workspace) use ($request): void {
            $workspace->setAttribute('current_user_role', $workspace->pivot->role ?? $workspace->roleFor($request->user()));
        });

        return response()->json(['data' => $workspaces]);
    }

    public function members(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);

        return response()->json(['data' => $workspace->load('owner:id,name,email')->members()->get()]);
    }

    public function invitations(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);

        return response()->json(['data' => $workspace->invitations()->whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get()]);
    }

    public function removeMember(RemoveWorkspaceMemberRequest $request, Workspace $workspace, User $member, RemoveWorkspaceMember $action): JsonResponse
    {
        $action->execute($request->user(), $workspace, $member);

        return response()->json(status: 204);
    }

    public function updateMemberRole(UpdateWorkspaceMemberRoleRequest $request, Workspace $workspace, User $member, UpdateWorkspaceMemberRole $action): JsonResponse
    {
        $action->execute($request->user(), $workspace, $member, $request->validated('role'));

        return response()->json(status: 204);
    }

    public function leave(LeaveWorkspaceRequest $request, Workspace $workspace, LeaveWorkspace $action): JsonResponse
    {
        $action->execute($request->user(), $workspace);

        return response()->json(status: 204);
    }
}
