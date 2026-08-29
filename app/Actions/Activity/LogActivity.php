<?php

namespace App\Actions\Activity;

use App\Models\ActivityLog;
use App\Models\Board;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

class LogActivity
{
    public function execute(User $actor, Workspace $workspace, string $action, ?Board $board = null, ?Model $subject = null, array $metadata = []): ActivityLog
    {
        return ActivityLog::create([
            'workspace_id' => $workspace->id,
            'board_id' => $board?->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
        ]);
    }
}
