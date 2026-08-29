<?php

namespace App\Actions\Tasks;

use App\Actions\Activity\LogActivity;
use App\Models\Task;
use App\Models\User;
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
        $title = $task->title;
        $task->delete();
        $this->logger->execute($user, $board->workspace, 'task.deleted', $board, null, ['task_title' => $title]);
    }
}
