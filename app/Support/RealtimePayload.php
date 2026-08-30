<?php

namespace App\Support;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Category;
use App\Models\Folder;
use App\Models\TaskComment;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Collection;

final class RealtimePayload
{
    /** @return array<string, int|string|null> */
    public static function workspace(Workspace $workspace): array
    {
        return [
            'id' => (int) $workspace->id,
            'name' => $workspace->name,
            'type' => $workspace->type,
            'owner_id' => (int) $workspace->owner_id,
            'updated_at' => $workspace->updated_at?->toISOString(),
        ];
    }

    /** @return array<string, bool|int|string|null> */
    public static function folder(Folder $folder): array
    {
        return [
            'id' => (int) $folder->id,
            'workspace_id' => (int) $folder->workspace_id,
            'parent_id' => $folder->parent_id === null ? null : (int) $folder->parent_id,
            'name' => $folder->name,
            'color' => $folder->color,
            'archived' => (bool) $folder->archived,
            'updated_at' => $folder->updated_at?->toISOString(),
        ];
    }

    /** @return array<string, bool|int|string|null> */
    public static function board(Board $board): array
    {
        return [
            'id' => (int) $board->id,
            'workspace_id' => (int) $board->workspace_id,
            'folder_id' => $board->folder_id === null ? null : (int) $board->folder_id,
            'name' => $board->name,
            'description' => $board->description,
            'color' => $board->color,
            'archived' => (bool) $board->archived,
            'updated_at' => $board->updated_at?->toISOString(),
        ];
    }

    /** @return array<string, bool|int|string|null> */
    public static function category(Category $category): array
    {
        return [
            'id' => (int) $category->id,
            'board_id' => (int) $category->board_id,
            'name' => $category->name,
            'color' => $category->color,
            'position' => (int) $category->position,
        ];
    }

    /** @return array<string, bool|int|string|null> */
    public static function column(BoardColumn $column): array
    {
        return [
            'id' => (int) $column->id,
            'board_id' => (int) $column->board_id,
            'name' => $column->name,
            'color' => $column->color,
            'position' => (int) $column->position,
        ];
    }

    /** @return array<string, int|string|null> */
    public static function member(User $member, ?string $role = null): array
    {
        return [
            'id' => (int) $member->id,
            'name' => $member->name,
            'last_name' => $member->last_name,
            'username' => $member->username,
            'role' => $role,
        ];
    }

    /** @return array<string, int|string|null> */
    public static function assignee(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'username' => $user->username,
        ];
    }

    /** @param Collection<int, User> $users */
    public static function assignees(Collection $users): array
    {
        return $users->map(fn (User $user): array => self::assignee($user))->values()->all();
    }

    /** @return array<string, bool|int|string|null|array<string, int|string|null>> */
    public static function comment(TaskComment $comment): array
    {
        $comment->loadMissing('author');

        return [
            'id' => (int) $comment->id,
            'task_id' => (int) $comment->task_id,
            'body' => $comment->body,
            'created_at' => $comment->created_at?->toISOString(),
            'updated_at' => $comment->updated_at?->toISOString(),
            'edited' => $comment->created_at !== null && $comment->updated_at !== null
                && ! $comment->created_at->equalTo($comment->updated_at),
            'author' => $comment->author ? self::assignee($comment->author) : null,
        ];
    }

    /** @return array<string, bool|int|string|null> */
    public static function invitation(WorkspaceInvitation $invitation): array
    {
        $workspace = $invitation->workspace;

        return [
            'id' => (int) $invitation->id,
            'token' => $invitation->token,
            'workspace_id' => (int) $invitation->workspace_id,
            'workspace_name' => $workspace->name,
            'owner_name' => $workspace->owner?->name,
            'role' => $invitation->role,
            'expires_at' => $invitation->expires_at?->toISOString(),
        ];
    }
}
