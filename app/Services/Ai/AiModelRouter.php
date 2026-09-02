<?php

namespace App\Services\Ai;

class AiModelRouter
{
    /**
     * Create a new class instance.
     */
    public function resolve(string $level): array
    {
        return config("ai.models.{$level}") ?? throw new \InvalidArgumentException('Livello AI non valido.');
    }
}
