<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

class OpenAiClient
{
    public function generate(array $input, array $schema, array $model): array
    {
        if (! config('ai.api_key')) {
            throw new \RuntimeException('ai_provider_not_configured');
        }
        $response = Http::withToken(config('ai.api_key'))->timeout(config('ai.timeout', 120))->post(rtrim(config('ai.base_url'), '/').'/responses', ['model' => $model['model'], 'reasoning' => ['effort' => $model['effort']], 'store' => false, 'input' => $input, 'text' => ['format' => ['type' => 'json_schema', 'name' => 'ai_result', 'strict' => true, 'schema' => $schema]]]);
        if ($response->failed()) {
            throw new \RuntimeException($response->status() >= 500 ? 'ai_provider_unavailable' : 'ai_provider_error');
        }
        $json = $response->json();
        if (($json['status'] ?? null) === 'incomplete' || isset($json['incomplete_details'])) {
            throw new \RuntimeException('ai_provider_incomplete');
        }
        foreach ($json['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $content) {
                if (isset($content['refusal']) || ($content['type'] ?? null) === 'refusal') {
                    throw new \RuntimeException('ai_provider_refused');
                }
            }
        }
        $text = $json['output_text'] ?? $json['output'][0]['content'][0]['text'] ?? null;
        if (! is_string($text)) {
            throw new \RuntimeException('ai_provider_invalid_response');
        }
        try {
            $result = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('ai_provider_invalid_response');
        }
        if (! is_array($result)) {
            throw new \RuntimeException('ai_provider_invalid_response');
        }

        return ['result' => $result, 'usage' => $json['usage'] ?? []];
    }
}
