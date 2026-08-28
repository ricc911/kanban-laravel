<?php

namespace App\Http\Controllers\Api;

use App\Actions\Boards\CreateBoard;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBoardRequest;
use App\Models\Folder;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Models\Board;

class BoardController extends Controller
{
    public function store(StoreBoardRequest $request, Workspace $workspace, CreateBoard $action): JsonResponse
    {
        $folder = $request->filled('folder_id') ? Folder::find($request->validated('folder_id')) : null;

        return response()->json(['data' => $action->execute($request->user(), $workspace, $request->validated('name'), $folder)], 201);
    }

    public function index(
        Request $request,
        Workspace $workspace
    ): JsonResponse {
        Gate::authorize('view', $workspace);

        $boards = $workspace->boards()
            ->with('folder:id,name')
            ->withCount('tasks')
            ->orderBy('name')
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
            'columns' => fn($query) => $query
                ->orderBy('position')
                ->with([
                    'tasks' => fn($query) => $query
                        ->where('archived', false)
                        ->orderBy('position'),
                ]),
            'categories' => fn($query) => $query
                ->orderBy('position'),
            'folder:id,name',
        ]);

        return response()->json([
            'data' => $board,
        ]);
    }

}
