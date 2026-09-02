<?php

namespace Tests\Unit;

use App\Services\Ai\AiCreditCalculator;
use Tests\TestCase;

class AiCreditCalculatorTest extends TestCase
{
    public function test_calculates_integer_credits_and_has_minimum_one(): void
    {
        $calculator = app(AiCreditCalculator::class);
        $pricing = ['input_price' => 2000000, 'output_price' => 12000000];

        $this->assertSame(1, $calculator->calculate(0, 0, $pricing));
        $this->assertSame(4, $calculator->calculate(1000, 100, $pricing));
        $this->assertSame(4000000, $calculator->costMicros(1000000, 200000, ['input_price' => 2000000, 'output_price' => 10000000]));
    }

    public function test_large_token_values_remain_integer_safe(): void
    {
        $credits = app(AiCreditCalculator::class)->calculate(10000000, 10000000, ['input_price' => 4000000, 'output_price' => 20000000]);

        $this->assertSame(240000, $credits);
        $this->assertIsInt($credits);
    }
}
