<?php

namespace App\Http\Controllers\Api;

use App\Actions\Workspaces\CreateSharedWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSharedWorkspaceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
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

        return response()->json([
            'data' => $workspaces,
        ]);
    }
}
