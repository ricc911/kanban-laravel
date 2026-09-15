<?php

namespace App\Services\Plans;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Collection;

class PublicPlanCatalog
{
    /**
     * @return Collection<int, Plan>
     */
    public function commercial(): Collection
    {
        $order = ['free', 'pro', 'team', 'business'];

        return Plan::query()
            ->whereIn('slug', $order)
            ->get()
            ->sortBy(fn (Plan $plan): int => array_search($plan->slug, $order, true))
            ->values();
    }
}
