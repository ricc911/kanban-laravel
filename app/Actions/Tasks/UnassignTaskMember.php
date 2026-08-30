<?php

namespace App\Actions\Tasks;

use App\Actions\Activity\LogActivity;
use App\Events\TaskAssigneesChanged;
use App\Models\Task;
use App\Models\User;
use App\Support\RealtimePayload;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UnassignTaskMember
{
    public function __construct(private LogActivity $logger) {}

    public function execute(User $actor, Task $task, User $assignee): Task
    {
        $workspace = $task->board->workspace;
        if (! $workspace->canEditContent($actor)) {
            abort(Response::HTTP_FORBIDDEN, 'Non hai il permesso di modificare gli assegnatari.');
        }

        DB::transaction(function () use ($actor, $task, $assignee, $workspace): void {
            $removed = DB::table('task_assignees')
                ->where('task_id', $task->id)
                ->where('user_id', $assignee->id)
                ->delete();
            if ($removed === 0) {
                return;
            }

            $task->load('assignees');
            $this->logger->execute($actor, $workspace, 'task.assignee_removed', $task->board, $task, [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'assignee_id' => $assignee->id,
                'assignee_name' => $assignee->name,
                'assignee_last_name' => $assignee->last_name,
                'assignee_username' => $assignee->username,
            ]);
            TaskAssigneesChanged::dispatch($task, RealtimePayload::assignees($task->assignees));
        });

        return $task->fresh('assignees');
    }
}
