<?php

namespace App\Http\Controllers\Api;

use App\Actions\Workspaces\AcceptWorkspaceInvitation;
use App\Actions\Workspaces\InviteWorkspaceMember;
use App\Actions\Workspaces\RejectWorkspaceInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptWorkspaceInvitationRequest;
use App\Http\Requests\RejectWorkspaceInvitationRequest;
use App\Http\Requests\StoreWorkspaceInvitationRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => WorkspaceInvitation::query()
            ->where('email', User::normalizeEmail($request->user()->email))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->with('workspace.owner:id,name,email')
            ->orderBy('expires_at')
            ->get()]);
    }

    public function store(StoreWorkspaceInvitationRequest $request, Workspace $workspace, InviteWorkspaceMember $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $workspace, $request->validated('email'), $request->validated('role') ?? 'member')], 201);
    }

    public function accept(AcceptWorkspaceInvitationRequest $request, AcceptWorkspaceInvitation $action, string $token): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $token)]);
    }

    public function reject(RejectWorkspaceInvitationRequest $request, WorkspaceInvitation $invitation, RejectWorkspaceInvitation $action): JsonResponse
    {
        $action->execute($request->user(), $invitation);

        return response()->json(status: 204);
    }
}
