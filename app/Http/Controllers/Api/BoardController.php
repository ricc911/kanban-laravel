<?php

namespace App\Http\Controllers\Api;

use App\Actions\Boards\ArchiveBoard;
use App\Actions\Boards\CreateBoard;
use App\Actions\Boards\DeleteBoard;
use App\Actions\Boards\MoveBoard;
use App\Actions\Boards\UpdateBoard;
use App\Http\Controllers\Controller;
use App\Http\Requests\ArchiveBoardRequest;
use App\Http\Requests\DestroyBoardRequest;
use App\Http\Requests\MoveBoardRequest;
use App\Http\Requests\StoreBoardRequest;
use App\Http\Requests\UpdateBoardRequest;
use App\Models\Board;
use App\Models\Folder;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BoardController extends Controller
{
    public function destroy(DestroyBoardRequest $request, Board $board, DeleteBoard $action): JsonResponse
    {
        $action->execute($request->user(), $board);

        return response()->json(status: 204);
    }

    public function store(StoreBoardRequest $request, Workspace $workspace, CreateBoard $action): JsonResponse
    {
        $folder = $request->filled('folder_id') ? Folder::find($request->validated('folder_id')) : null;

        return response()->json(['data' => $action->execute($request->user(), $workspace, $request->validated('name'), $folder, $request->validated('color'))], 201);
    }

    public function update(UpdateBoardRequest $request, Board $board, UpdateBoard $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $board, $request->validated('name'), $request->validated('description'), $request->validated('color'))]);
    }

    public function move(MoveBoardRequest $request, Board $board, MoveBoard $action): JsonResponse
    {
        $folder = $request->filled('folder_id') ? Folder::find($request->validated('folder_id')) : null;
        $archived = $request->has('archived') ? $request->boolean('archived') : null;

        return response()->json(['data' => $action->execute($request->user(), $board, $folder, $archived)]);
    }

    public function archive(ArchiveBoardRequest $request, Board $board, ArchiveBoard $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $board, $request->validated('archived'))]);
    }

    public function index(
        Request $request,
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize('view', $workspace);

        $boards = $workspace->boards()
            ->with('folder:id,name')
            ->withCount('tasks')
            ->orderBy('archived')
            ->latest('updated_at')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => $boards,
        ]);
    }

    public function show(
        Request $request,
        Board $board
    ): JsonResponse {
        Gate::authorize('view', $board->workspace);

        $board->load([
            'columns' => fn ($query) => $query
                ->orderBy('position')
                ->with([
                    'tasks' => fn ($query) => $query
                        ->where('archived', false)
                        ->orderBy('position'),
                ]),
            'categories' => fn ($query) => $query
                ->orderBy('position'),
            'folder:id,name',
        ]);

        return response()->json([
            'data' => $board,
        ]);
    }
}
