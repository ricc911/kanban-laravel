<?php

namespace App\Http\Controllers\Api;

use App\Actions\Workspaces\AcceptWorkspaceInvitation;
use App\Actions\Workspaces\InviteWorkspaceMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptWorkspaceInvitationRequest;
use App\Http\Requests\StoreWorkspaceInvitationRequest;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;

class InvitationController extends Controller
{
    public function store(StoreWorkspaceInvitationRequest $request, Workspace $workspace, InviteWorkspaceMember $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $workspace, $request->validated('email'))], 201);
    }

    public function accept(AcceptWorkspaceInvitationRequest $request, AcceptWorkspaceInvitation $action, string $token): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $token)]);
    }
}
