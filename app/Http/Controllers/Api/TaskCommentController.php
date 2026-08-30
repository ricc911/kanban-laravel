<?php

namespace App\Http\Controllers\Api;

use App\Events\TaskCommentCreated;
use App\Events\TaskCommentDeleted;
use App\Events\TaskCommentUpdated;
use App\Events\UserRealtimeEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskCommentRequest;
use App\Http\Requests\UpdateTaskCommentRequest;
use App\Models\Task;
use App\Models\TaskComment;
use App\Notifications\TaskCommentCreatedNotification;
use App\Support\NotificationPayload;
use App\Support\RealtimePayload;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

class TaskCommentController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        Gate::authorize('viewAny', [TaskComment::class, $task]);
        $comments = $task->comments()->with('author:id,name,last_name,username')
            ->latest('created_at')->latest('id')->limit(50)->get()
            ->sortBy(fn (TaskComment $comment): array => [$comment->created_at?->timestamp ?? 0, $comment->id])
            ->values()->map(fn (TaskComment $comment): array => RealtimePayload::comment($comment));

        return response()->json(['comments' => $comments]);
    }

    public function store(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('create', [TaskComment::class, $task]);
        $comment = $task->comments()->create(['user_id' => $request->user()->id, 'body' => $request->validated('body')]);
        $comment->load('author');
        $task->load('assignees');
        foreach ($task->assignees as $assignee) {
            if ((int) $assignee->id === (int) $request->user()->id) {
                continue;
            }
            $notification = new TaskCommentCreatedNotification($task, $comment, $request->user());
            $notification->id = (string) str()->uuid();
            $assignee->notify($notification);
            $storedNotification = $assignee->notifications()->find($notification->id);
            if ($storedNotification !== null) {
                UserRealtimeEvent::dispatch('notification.created', (int) $assignee->id, [
                    'notification' => NotificationPayload::database($storedNotification),
                ]);
            }
        }
        $count = $task->comments()->count();
        try {
            Event::dispatch(new TaskCommentCreated($comment, (int) $task->board_id, $count));
        } catch (BroadcastException|GuzzleException) {
        }

        return response()->json(['data' => RealtimePayload::comment($comment), 'comments_count' => $count], 201);
    }

    public function update(UpdateTaskCommentRequest $request, TaskComment $comment): JsonResponse
    {
        Gate::authorize('update', $comment);
        $comment->body = $request->validated('body');
        if ($comment->isDirty('body')) {
            $comment->save();
            $comment->load(['author', 'task']);
            try {
                Event::dispatch(new TaskCommentUpdated($comment, (int) $comment->task->board_id, $comment->task->comments()->count()));
            } catch (BroadcastException|GuzzleException) {
            }
        }

        return response()->json(['data' => RealtimePayload::comment($comment)]);
    }

    public function destroy(TaskComment $comment): Response
    {
        Gate::authorize('delete', $comment);
        $task = $comment->task;
        $commentId = (int) $comment->id;
        $comment->delete();
        $count = $task->comments()->count();
        try {
            Event::dispatch(new TaskCommentDeleted($commentId, (int) $task->id, (int) $task->board_id, $count));
        } catch (BroadcastException|GuzzleException) {
        }

        return response()->noContent();
    }
}
