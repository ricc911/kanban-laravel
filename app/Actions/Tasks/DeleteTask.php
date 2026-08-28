<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteTask
{
    public function execute(
        User $user,
        Task $task
    ): void {
        if (! $task->board->workspace->hasMember($user)) {
            throw ValidationException::withMessages([
                'task' => 'Non hai accesso a questa task.',
            ]);
        }

        $task->delete();
    }
}
