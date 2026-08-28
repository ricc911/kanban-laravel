<?php

namespace App\Http\Controllers\Api;

use App\Actions\BoardColumns\CreateBoardColumn;
use App\Actions\BoardColumns\DeleteBoardColumn;
use App\Actions\BoardColumns\MoveBoardColumn;
use App\Actions\BoardColumns\ReorderBoardColumns;
use App\Actions\BoardColumns\UpdateBoardColumn;
use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyBoardColumnRequest;
use App\Http\Requests\MoveBoardColumnRequest;
use App\Http\Requests\ReorderBoardColumnsRequest;
use App\Http\Requests\StoreBoardColumnRequest;
use App\Http\Requests\UpdateBoardColumnRequest;
use App\Models\Board;
use App\Models\BoardColumn;
use Illuminate\Http\JsonResponse;

class BoardColumnController extends Controller
{
    public function store(StoreBoardColumnRequest $request, Board $board, CreateBoardColumn $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $board, $request->validated('name'))], 201);
    }

    public function update(UpdateBoardColumnRequest $request, BoardColumn $column, UpdateBoardColumn $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $column, $request->validated('name'))]);
    }

    public function move(MoveBoardColumnRequest $request, BoardColumn $column, MoveBoardColumn $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $column, $request->validated('position'))]);
    }

    public function reorder(ReorderBoardColumnsRequest $request, Board $board, ReorderBoardColumns $action): JsonResponse
    {
        $action->execute($request->user(), $board, $request->validated('column_ids'));

        return response()->json(['message' => 'Colonne riordinate.']);
    }

    public function destroy(DestroyBoardColumnRequest $request, BoardColumn $column, DeleteBoardColumn $action): JsonResponse
    {
        $action->execute($request->user(), $column);

        return response()->json(status: 204);
    }
}
