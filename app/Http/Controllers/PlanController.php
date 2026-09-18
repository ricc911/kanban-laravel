<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Services\Ai\AiUsageService;
use App\Services\Plans\PlanLimitService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request, PlanLimitService $planLimits, AiUsageService $aiUsage): View
    {
        /** @var User $user */
        $user = $request->user();
        $subscription = $user->subscription()->with('plan')->firstOrFail();
        $currentPlan = $subscription->plan;
        $catalogOrder = ['free', 'pro', 'team', 'business'];
        $catalog = Plan::query()
            ->whereIn('slug', $catalogOrder)
            ->get()
            ->sortBy(fn (Plan $plan): int => array_search($plan->slug, $catalogOrder, true))
            ->values();
        $aiStatus = $aiUsage->statusForSubscription($subscription);

        return view('plans.index', [
            'currentPlan' => $currentPlan,
            'currentPrice' => $currentPlan->slug === 'unlimited'
                ? 'Piano interno'
                : $this->formatPrice($currentPlan->price_cents),
            'catalog' => $catalog,
            'usage' => [
                'projects' => ['used' => $planLimits->ownedProjectCount($user), 'limit' => $currentPlan->max_projects],
                'shared_workspaces' => ['used' => $planLimits->ownedSharedWorkspaceCount($user), 'limit' => $currentPlan->max_shared_workspaces],
                'ai' => $aiStatus,
            ],
        ]);
    }

    private function formatPrice(?int $priceCents): string
    {
        if ($priceCents === null) {
            return 'Piano interno';
        }

        return $priceCents === 0
            ? '€0'
            : '€'.number_format($priceCents / 100, 2, ',', '.').' / mese';
    }
}
