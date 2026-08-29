<?php

namespace App\Actions\Activity;

use App\Events\ActivityLogged;
use App\Models\ActivityLog;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RealtimePayload;
use Illuminate\Database\Eloquent\Model;

class LogActivity
{
    public function execute(User $actor, Workspace $workspace, string $action, ?Board $board = null, ?Model $subject = null, array $metadata = []): ActivityLog
    {
        $activity = ActivityLog::create([
            'workspace_id' => $workspace->id,
            'board_id' => $board?->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
        ]);

        ActivityLogged::dispatch(
            (int) $workspace->id,
            $board?->id === null ? null : (int) $board->id,
            [
                'id' => (int) $activity->id,
                'action' => $activity->action,
                'actor' => [
                    'id' => (int) $actor->id,
                    'name' => $actor->name,
                ],
                'board' => $board ? RealtimePayload::board($board) : null,
                'metadata' => $activity->metadata ?? [],
                'created_at' => $activity->created_at?->toISOString(),
            ],
        );

        return $activity;
    }
}
