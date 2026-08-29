<?php

namespace App\Actions\Tasks;

use App\Actions\Activity\LogActivity;
use App\Events\TaskDeleted;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteTask
{
    public function __construct(private LogActivity $logger) {}

    public function execute(
        User $user,
        Task $task
    ): void {
        if (! $task->board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'task' => 'Non hai accesso a questa task.',
            ]);
        }

        $board = $task->board;
        $taskId = (int) $task->id;
        $boardId = (int) $board->id;
        $title = $task->title;

        DB::transaction(function () use ($user, $task, $board, $taskId, $boardId, $title): void {
            $task->delete();
            $this->logger->execute($user, $board->workspace, 'task.deleted', $board, null, ['task_title' => $title]);
            TaskDeleted::dispatch($taskId, $boardId);
        });
    }
}
