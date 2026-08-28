<?php

namespace App\Http\Controllers\Api;

use App\Actions\Folders\CreateFolder;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFolderRequest;
use App\Models\Folder;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FolderController extends Controller
{
    public function store(StoreFolderRequest $request, Workspace $workspace, CreateFolder $action): JsonResponse
    {
        $parent = $request->filled('parent_id') ? Folder::find($request->validated('parent_id')) : null;

        return response()->json(['data' => $action->execute($request->user(), $workspace, $request->validated('name'), $parent)], 201);
    }

    public function index(
        Request $request,
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize('view', $workspace);

        $folders = $workspace->folders()
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $folders,
        ]);
    }

}
