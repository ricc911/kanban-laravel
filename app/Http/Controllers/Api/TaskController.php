<?php

namespace App\Http\Controllers\Api;

use App\Actions\Tasks\CreateTask;
use App\Actions\Tasks\DeleteTask;
use App\Actions\Tasks\MoveTask;
use App\Actions\Tasks\ReorderTasks;
use App\Actions\Tasks\UpdateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyTaskRequest;
use App\Http\Requests\MoveTaskRequest;
use App\Http\Requests\ReorderTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Category;
use App\Models\Task;
use Illuminate\Http\JsonResponse;

class TaskController extends Controller
{
    public function store(StoreTaskRequest $request, Board $board, BoardColumn $column, CreateTask $action): JsonResponse
    {
        $category = $request->filled('category_id') ? Category::find($request->validated('category_id')) : null;

        return response()->json(['data' => $action->execute(
            $request->user(),
            $board,
            $column,
            $request->validated('title'),
            $request->validated('description'),
            $category,
            $request->validated('priority'),
            $request->validated('due_at'),
            $request->validated('color'),
        )], 201);
    }

    public function update(UpdateTaskRequest $request, Task $task, UpdateTask $action): JsonResponse
    {
        $data = $request->validated();
        $category = isset($data['category_id']) ? Category::findOrFail($data['category_id']) : null;

        return response()->json(['data' => $action->execute(
            $request->user(),
            $task,
            $data['title'] ?? $task->title,
            $data['description'] ?? null,
            $data['priority'] ?? null,
            $data['due_at'] ?? null,
            $category,
            $data['color'] ?? null,
            array_keys($data),
        )]);
    }

    public function move(MoveTaskRequest $request, Task $task, MoveTask $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($request->user(), $task, BoardColumn::find($request->validated('target_column_id')), $request->validated('position'))]);
    }

    public function reorder(ReorderTasksRequest $request, BoardColumn $column, ReorderTasks $action): JsonResponse
    {
        $action->execute($request->user(), $column, $request->validated('task_ids'));

        return response()->json(['message' => 'Task riordinate.']);
    }

    public function destroy(DestroyTaskRequest $request, Task $task, DeleteTask $action): JsonResponse
    {
        $action->execute($request->user(), $task);

        return response()->json(status: 204);
    }
}
