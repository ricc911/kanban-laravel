<?php

namespace App\Http\Controllers\Api;

use App\Actions\Folders\ArchiveFolder;
use App\Actions\Folders\CreateFolder;
use App\Actions\Folders\DeleteFolder;
use App\Actions\Folders\MoveFolder;
use App\Actions\Folders\UpdateFolder;
use App\Http\Controllers\Controller;
use App\Http\Requests\ArchiveFolderRequest;
use App\Http\Requests\DestroyFolderRequest;
use App\Http\Requests\MoveFolderRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFolderRequest;
use App\Models\Folder;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FolderController extends Controller
{
    public function destroy(DestroyFolderRequest $request, Folder $folder, DeleteFolder $action): JsonResponse
    {
        $action->execute($request->user(), $folder);

        return response()->json(status: 204);
    }

    public function store(StoreFolderRequest $request, Workspace $workspace, CreateFolder $action): JsonResponse
    {
        $parent = $request->filled('parent_id') ? Folder::find($request->validated('parent_id')) : null;

        return response()->json(['data' => $action->execute($request->user(), $workspace, $request->validated('name'), $parent, $request->validated('color'))], 201);
    }

    public function update(UpdateFolderRequest $request, Folder $folder, UpdateFolder $action): JsonResponse
    {
        $data = $request->validated();

        return response()->json(['data' => $action->execute(
            $request->user(),
            $folder,
            $data['name'] ?? $folder->name,
            $data['color'] ?? null,
            array_keys($data),
        )]);
    }

    public function move(MoveFolderRequest $request, Folder $folder, MoveFolder $action): JsonResponse
    {
        $parent = $request->filled('parent_id') ? Folder::find($request->validated('parent_id')) : null;

        return response()->json(['data' => $action->execute($request->user(), $folder, $parent)]);
    }

    public function archive(ArchiveFolderRequest $request, Folder $folder, ArchiveFolder $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $folder, $request->validated('archived'))]);
    }

    public function index(
        Request $request,
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize('view', $workspace);

        $folders = $workspace->folders()
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('parent_id')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $folders,
        ]);
    }
}
