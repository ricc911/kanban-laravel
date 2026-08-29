<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'type',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function boards(): HasMany
    {
        return $this->hasMany(Board::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function hasMember(User $user): bool
    {
        return $this->members()
            ->where('users.id', $user->id)
            ->exists();
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function roleFor(User $user): ?string
    {
        if ($this->isOwner($user)) {
            return 'owner';
        }

        return $this->members()->whereKey($user->id)->first()?->pivot?->role;
    }

    public function canEditContent(User $user): bool
    {
        return in_array($this->roleFor($user), ['owner', 'admin', 'member'], true);
    }

    public function canManageMembers(User $user): bool
    {
        return $this->type === 'shared'
            && in_array($this->roleFor($user), ['owner', 'admin'], true);
    }

    public function canManageMember(User $actor, User $target): bool
    {
        $actorRole = $this->roleFor($actor);
        $targetRole = $this->roleFor($target);

        if ($this->type !== 'shared' || $target->id === $this->owner_id || $targetRole === null) {
            return false;
        }

        return $actorRole === 'owner'
            || ($actorRole === 'admin' && in_array($targetRole, ['member', 'viewer'], true));
    }

    public function isViewer(User $user): bool
    {
        return $this->roleFor($user) === 'viewer';
    }
}
