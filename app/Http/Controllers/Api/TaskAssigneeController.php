<?php

namespace App\Http\Controllers\Api;

use App\Actions\Tasks\AssignTaskMember;
use App\Actions\Tasks\UnassignTaskMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskAssigneeRequest;
use App\Models\Task;
use App\Models\User;
use App\Support\RealtimePayload;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\JsonResponse;

class TaskAssigneeController extends Controller
{
    public function store(StoreTaskAssigneeRequest $request, Task $task, AssignTaskMember $action): JsonResponse
    {
        try {
            $updatedTask = $action->execute($request->user(), $task, User::findOrFail($request->integer('user_id')));
        } catch (BroadcastException|GuzzleException) {
            $updatedTask = $task->fresh('assignees');
        }

        return response()->json(['data' => [
            'task_id' => (int) $updatedTask->id,
            'assignees' => RealtimePayload::assignees($updatedTask->assignees),
        ]]);
    }

    public function destroy(StoreTaskAssigneeRequest $request, Task $task, User $user, UnassignTaskMember $action): JsonResponse
    {
        try {
            $updatedTask = $action->execute($request->user(), $task, $user);
        } catch (BroadcastException|GuzzleException) {
            $updatedTask = $task->fresh('assignees');
        }

        return response()->json(['data' => [
            'task_id' => (int) $updatedTask->id,
            'assignees' => RealtimePayload::assignees($updatedTask->assignees),
        ]]);
    }
}
