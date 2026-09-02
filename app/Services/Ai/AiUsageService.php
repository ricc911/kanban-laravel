<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Models\Board;
use App\Models\Subscription;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiUsageService
{
    public function reserve(Board $board, int $userId, string $requestId, string $feature, string $level, array $model, int $inputBytes, int $maxOutputTokens): AiUsageLog
    {
        try {
            return DB::transaction(function () use ($board, $userId, $requestId, $feature, $level, $model, $inputBytes, $maxOutputTokens): AiUsageLog {
                if (AiUsageLog::where('request_id', $requestId)->exists()) {
                    throw new HttpResponseException(response()->json(['code' => 'ai_request_already_processed'], 409));
                }
                $subscription = $board->workspace->owner->subscription()->with('plan')->lockForUpdate()->first();
                $plan = $subscription?->plan;
                if (! $plan?->ai_enabled) {
                    throw new HttpResponseException(response()->json(['code' => 'ai_disabled'], 403));
                }
                [$start, $end] = app(AiBillingPeriodResolver::class)->resolve();
                $used = (int) AiUsageLog::where('subscription_id', $subscription->id)->where('period_start', '>=', $start)->where('period_end', '<=', $end)->where('status', 'completed')->sum('credits_used');
                $reserved = (int) AiUsageLog::where('subscription_id', $subscription->id)->where('period_start', '>=', $start)->where('period_end', '<=', $end)->where('status', 'pending')->where('expires_at', '>', now())->sum('reserved_credits');
                $estimate = app(AiCreditCalculator::class)->calculate($inputBytes, $maxOutputTokens, $model);
                if ($used + $reserved + $estimate > (int) $plan->ai_monthly_credits) {
                    throw new HttpResponseException(response()->json(['code' => 'ai_credits_exhausted'], 429));
                }

                return AiUsageLog::create(['request_id' => $requestId, 'subscription_id' => $subscription->id, 'workspace_id' => $board->workspace_id, 'user_id' => $userId, 'feature' => $feature, 'reasoning_level' => $level, 'model' => $model['model'], 'status' => 'pending', 'reserved_credits' => $estimate, 'period_start' => $start, 'period_end' => $end, 'expires_at' => now()->addMinutes(5)]);
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'request_id')) {
                throw new HttpResponseException(response()->json(['code' => 'ai_request_already_processed'], 409));
            }

            throw $exception;
        }
    }

    public function complete(AiUsageLog $usage, array $providerUsage, AiCreditCalculator $calculator, array $model): AiUsageLog
    {
        return DB::transaction(function () use ($usage, $providerUsage, $calculator, $model): AiUsageLog {
            $usage = AiUsageLog::lockForUpdate()->findOrFail($usage->id);
            $input = (int) ($providerUsage['input_tokens'] ?? 0);
            $output = (int) ($providerUsage['output_tokens'] ?? 0);
            $credits = $calculator->calculate($input, $output, $model);
            $reasoning = isset($providerUsage['output_tokens_details']['reasoning_tokens']) ? (int) $providerUsage['output_tokens_details']['reasoning_tokens'] : null;
            $actualCost = $calculator->costMicros($input, $output, $model);
            if ($credits > $usage->reserved_credits) {
                Log::warning('AI usage exceeded reservation.', ['request_id' => $usage->request_id, 'feature' => $usage->feature, 'model' => $usage->model]);
            }
            $cachedInput = $providerUsage['cached_input_tokens'] ?? $providerUsage['input_tokens_details']['cached_tokens'] ?? null;
            $usage->update(['status' => 'completed', 'reserved_credits' => 0, 'credits_used' => $credits, 'input_tokens' => $input, 'output_tokens' => $output, 'reasoning_tokens' => $reasoning, 'cached_input_tokens' => $cachedInput === null ? null : (int) $cachedInput, 'provider_cost_micros' => $actualCost, 'completed_at' => now()]);

            return $usage->fresh();
        });
    }

    public function fail(AiUsageLog $usage): void
    {
        $usage->update(['status' => 'failed', 'reserved_credits' => 0, 'credits_used' => 0]);
    }

    public function remaining(AiUsageLog $usage): int
    {
        return $this->statusForSubscription($usage->subscription)->remaining_credits;
    }

    public function statusForSubscription(?Subscription $subscription): object
    {
        [$start, $end] = app(AiBillingPeriodResolver::class)->resolve();
        $limit = (int) ($subscription?->loadMissing('plan')->plan?->ai_monthly_credits ?? 0);
        $query = AiUsageLog::query()->where('subscription_id', $subscription?->id)->where('period_start', '>=', $start)->where('period_end', '<=', $end);
        $used = (int) (clone $query)->where('status', 'completed')->sum('credits_used');
        $reserved = (int) (clone $query)->where('status', 'pending')->where('expires_at', '>', now())->sum('reserved_credits');

        return (object) ['monthly_credits' => $limit, 'used_credits' => $used, 'reserved_credits' => $reserved, 'remaining_credits' => max(0, $limit - $used - $reserved), 'period_start' => $start, 'period_end' => $end];
    }
}
