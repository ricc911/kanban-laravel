<?php

namespace Tests\Unit;

use App\Services\Ai\AiModelRouter;
use Tests\TestCase;

class AiModelRouterTest extends TestCase
{
    public function test_resolves_each_reasoning_level_from_config(): void
    {
        $router = app(AiModelRouter::class);

        foreach (['low', 'medium', 'high'] as $level) {
            $resolved = $router->resolve($level);
            $this->assertSame(config("ai.models.$level.model"), $resolved['model']);
            $this->assertSame($level, $resolved['effort']);
        }
    }

    public function test_invalid_reasoning_level_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(AiModelRouter::class)->resolve('invalid');
    }
}
