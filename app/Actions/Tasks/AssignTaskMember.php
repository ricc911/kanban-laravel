<?php

namespace App\Actions\Tasks;

use App\Actions\Activity\LogActivity;
use App\Events\TaskAssigneesChanged;
use App\Events\UserRealtimeEvent;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Support\NotificationPayload;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AssignTaskMember
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $actor, Task $task, User $assignee): Task
    {
        $workspace = $task->board->workspace;
        if (! $workspace->canEditContent($actor)) {
            abort(Response::HTTP_FORBIDDEN, 'Non hai il permesso di modificare gli assegnatari.');
        }
        if (! $workspace->isOwner($assignee) && ! $workspace->hasMember($assignee)) {
            throw ValidationException::withMessages(['user_id' => 'Questo utente non appartiene al workspace.']);
        }

        DB::transaction(function () use ($actor, $task, $assignee, $workspace): void {
            $inserted = DB::table('task_assignees')->insertOrIgnore([
                'task_id' => $task->id,
                'user_id' => $assignee->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($inserted === 0) {
                return;
            }

            $task->load('assignees');
            $this->logger->execute($actor, $workspace, 'task.assignee_added', $task->board, $task, [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'assignee_id' => $assignee->id,
                'assignee_name' => $assignee->name,
                'assignee_last_name' => $assignee->last_name,
                'assignee_username' => $assignee->username,
            ]);
            TaskAssigneesChanged::dispatch($task, RealtimePayload::assignees($task->assignees));

            if ($actor->id !== $assignee->id) {
                $notification = new TaskAssignedNotification($task, $actor);
                $notification->id = (string) str()->uuid();
                $assignee->notify($notification);
                $storedNotification = $assignee->notifications()->find($notification->id);
                if ($storedNotification !== null) {
                    UserRealtimeEvent::dispatch('notification.created', (int) $assignee->id, [
                        'notification' => NotificationPayload::database($storedNotification),
                    ]);
                }
            }
        });

        return $task->fresh('assignees');
    }
}
