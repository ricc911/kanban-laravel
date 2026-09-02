<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    protected $fillable = ['request_id', 'subscription_id', 'workspace_id', 'user_id', 'feature', 'reasoning_level', 'provider', 'model', 'status', 'reserved_credits', 'input_tokens', 'output_tokens', 'reasoning_tokens', 'cached_input_tokens', 'provider_cost_micros', 'credits_used', 'period_start', 'period_end', 'expires_at', 'completed_at'];

    protected function casts(): array
    {
        return ['period_start' => 'datetime', 'period_end' => 'datetime', 'expires_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
