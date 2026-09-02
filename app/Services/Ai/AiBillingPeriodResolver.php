<?php

namespace App\Services\Ai;

class AiBillingPeriodResolver
{
    /**
     * Create a new class instance.
     */
    public function resolve(): array
    {
        $start = now()->startOfMonth();

        return [$start, $start->copy()->addMonth()->subSecond()];
    }
}
