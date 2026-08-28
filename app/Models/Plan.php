<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'max_shared_workspaces',
        'max_members_per_workspace',
        'max_projects',
        'ai_enabled',
        'ai_monthly_credits',
    ];

    protected function casts(): array
    {
        return [
            'max_shared_workspaces' => 'integer',
            'max_members_per_workspace' => 'integer',
            'max_projects' => 'integer',
            'ai_enabled' => 'boolean',
            'ai_monthly_credits' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
