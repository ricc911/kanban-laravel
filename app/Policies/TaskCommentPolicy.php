<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;

class TaskCommentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Task $task): bool
    {
        return $task->board->workspace->roleFor($user) !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TaskComment $taskComment): bool
    {
        return $this->viewAny($user, $taskComment->task);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Task $task): bool
    {
        return $task->board->workspace->canEditContent($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TaskComment $taskComment): bool
    {
        return (int) $taskComment->user_id === (int) $user->id
            && $taskComment->task->board->workspace->canEditContent($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TaskComment $taskComment): bool
    {
        $workspace = $taskComment->task->board->workspace;
        $role = $workspace->roleFor($user);

        return $role !== null
            && ((int) $taskComment->user_id === (int) $user->id || in_array($role, ['owner', 'admin'], true));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TaskComment $taskComment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TaskComment $taskComment): bool
    {
        return false;
    }
}
