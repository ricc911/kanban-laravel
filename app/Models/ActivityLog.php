<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = ['workspace_id', 'board_id', 'actor_id', 'action', 'subject_type', 'subject_id', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
