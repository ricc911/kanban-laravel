<?php

namespace App\Http\Controllers\Api;

use App\Events\TaskEditingStateChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTaskEditingStateRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TaskEditingController extends Controller
{
    public function update(UpdateTaskEditingStateRequest $request, Task $task): JsonResponse
    {
        $workspace = $task->board->workspace;

        if ($request->boolean('active') && ! $workspace->canEditContent($request->user())) {
            abort(Response::HTTP_FORBIDDEN);
        }

        TaskEditingStateChanged::dispatch(
            $task,
            $request->user(),
            $request->boolean('active'),
            $request->validated('session_id'),
        );

        return response()->json(['message' => 'Stato editing aggiornato.']);
    }
}
