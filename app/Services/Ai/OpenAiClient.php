<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        if (! is_array($json)) {
            $this->logInvalidResponse($response->status(), [], 'invalid_json_payload');
            throw new \RuntimeException('ai_provider_invalid_response');
        }
        if (($json['status'] ?? null) === 'incomplete' || isset($json['incomplete_details'])) {
            throw new \RuntimeException('ai_provider_incomplete');
        }
        if (($json['status'] ?? null) === 'failed') {
            throw new \RuntimeException('ai_provider_unavailable');
        }
        if (($json['status'] ?? null) === 'cancelled') {
            throw new \RuntimeException('ai_provider_unavailable');
        }
        if (isset($json['status']) && $json['status'] !== 'completed') {
            $this->logInvalidResponse($response->status(), $json, 'non_completed_status');
            throw new \RuntimeException('ai_provider_invalid_response');
        }
        $textSegments = [];
        foreach ($json['output'] ?? [] as $output) {
            if (($output['type'] ?? null) !== 'message') {
                continue;
            }
            foreach ($output['content'] ?? [] as $content) {
                if (isset($content['refusal']) || ($content['type'] ?? null) === 'refusal') {
                    throw new \RuntimeException('ai_provider_refused');
                }
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    $textSegments[] = $content['text'];
                }
            }
        }
        $text = $textSegments !== [] ? implode('', $textSegments) : ($json['output_text'] ?? null);
        if (! is_string($text)) {
            $this->logInvalidResponse($response->status(), $json, 'no_output_text');
            throw new \RuntimeException('ai_provider_invalid_response');
        }
        try {
            $result = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->logInvalidResponse($response->status(), $json, 'invalid_json');
            throw new \RuntimeException('ai_provider_invalid_response');
        }
        if (! is_array($result)) {
            throw new \RuntimeException('ai_provider_invalid_response');
        }

        return ['result' => $result, 'usage' => $json['usage'] ?? []];
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function logInvalidResponse(int $httpStatus, array $json, string $stage): void
    {
        $output = $json['output'] ?? null;
        $outputTypes = [];
        $contentTypes = [];

        if (is_array($output)) {
            foreach ($output as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $outputTypes[] = $item['type'] ?? null;
                foreach ($item['content'] ?? [] as $content) {
                    if (is_array($content)) {
                        $contentTypes[] = $content['type'] ?? null;
                    }
                }
            }
        }

        Log::warning('AI provider response could not be parsed.', [
            'stage' => $stage,
            'http_status' => $httpStatus,
            'response_status' => $json['status'] ?? null,
            'object' => $json['object'] ?? null,
            'has_output' => array_key_exists('output', $json),
            'output_count' => is_array($output) ? count($output) : null,
            'output_types' => array_values(array_unique(array_filter($outputTypes, 'is_string'))),
            'content_types' => array_values(array_unique(array_filter($contentTypes, 'is_string'))),
            'has_top_level_output_text' => is_string($json['output_text'] ?? null),
            'has_usage' => is_array($json['usage'] ?? null),
        ]);
    }
}
