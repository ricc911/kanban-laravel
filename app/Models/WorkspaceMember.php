<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Validation\ValidationException;

class WorkspaceMember extends Pivot
{
    protected static function booted(): void
    {
        static::creating(function (WorkspaceMember $membership): void {
            $workspace = Workspace::query()->findOrFail($membership->workspace_id);

            if ($workspace->type === 'personal' && (int) $workspace->owner_id !== (int) $membership->user_id) {
                throw ValidationException::withMessages([
                    'workspace' => Workspace::PERSONAL_SHARING_MESSAGE,
                ]);
            }
        });
    }
}
