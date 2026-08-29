<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ActivityLogController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);

        $query = $workspace->activityLogs()
            ->with(['actor:id,name', 'board:id,name'])
            ->latest('created_at')
            ->latest('id');

        if ($request->filled('board_id')) {
            $board = Board::findOrFail($request->integer('board_id'));
            abort_unless($board->workspace_id === $workspace->id, 404);
            $query->where('board_id', $board->id);
        }

        return response()->json($query->paginate(25));
    }
}
