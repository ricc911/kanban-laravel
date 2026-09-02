<?php

namespace App\Services\Ai;

class AiCreditCalculator
{
    /**
     * Create a new class instance.
     */
    public function calculate(int $inputTokens, int $outputTokens, array $pricing): int
    {
        return max(1, (int) ceil($this->costMicros($inputTokens, $outputTokens, $pricing) / 1000));
    }

    public function costMicros(int $inputTokens, int $outputTokens, array $pricing): int
    {
        $inputCost = intdiv($inputTokens * (int) $pricing['input_price'] + 999999, 1000000);
        $outputCost = intdiv($outputTokens * (int) $pricing['output_price'] + 999999, 1000000);

        return $inputCost + $outputCost;
    }
}
