<?php

namespace App\Http\Controllers\Api;

use App\Actions\Ai\ApplyAiTaskBreakdown;
use App\Actions\Tasks\CreateTask;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiBreakdownRequest;
use App\Http\Requests\AiGenerateTaskDescriptionRequest;
use App\Http\Requests\AiProjectRequest;
use App\Http\Requests\ApplyAiTaskBreakdownRequest;
use App\Models\AiUsageLog;
use App\Models\Board;
use App\Models\Task;
use App\Services\Ai\AiCreditCalculator;
use App\Services\Ai\AiModelRouter;
use App\Services\Ai\AiProductContext;
use App\Services\Ai\AiProjectContextBuilder;
use App\Services\Ai\AiUsageService;
use App\Services\Ai\OpenAiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AiController extends Controller
{
    public function status(Request $request, Board $board, AiUsageService $usage): JsonResponse
    {
        Gate::authorize('view', $board->workspace);
        $plan = $board->workspace->owner->subscription?->plan;
        $status = $usage->statusForSubscription($board->workspace->owner->subscription);

        return response()->json(['enabled' => (bool) $plan?->ai_enabled, 'can_use' => $board->workspace->canEditContent($request->user()) && (bool) $plan?->ai_enabled, 'monthly_credits' => $status->monthly_credits, 'used_credits' => $status->used_credits, 'reserved_credits' => $status->reserved_credits, 'remaining_credits' => $status->remaining_credits, 'reasoning_levels' => ['low', 'medium', 'high']]);
    }

    public function generateDescription(AiGenerateTaskDescriptionRequest $request, Task $task, AiModelRouter $router, OpenAiClient $client, AiUsageService $usage, AiCreditCalculator $calculator, AiProductContext $productContext): JsonResponse
    {
        $board = $task->board;
        abort_unless($board->workspace->canEditContent($request->user()), 403);
        $model = $router->resolve($request->validated('reasoning_level'));
        $input = json_encode($task->only(['title', 'description', 'priority', 'due_at']), JSON_THROW_ON_ERROR);
        $reserved = $usage->reserve($board, $request->user()->id, $request->validated('request_id'), 'generate_description', $request->validated('reasoning_level'), $model, strlen($input), 800);

        try {
            $result = $client->generate([['role' => 'system', 'content' => $productContext->systemPrompt('Genera una descrizione concisa e concreta per la task corrente. Restituisci solamente una proposta senza inventare dettagli.')], ['role' => 'user', 'content' => $input]], ['type' => 'object', 'properties' => ['description' => ['type' => 'string']], 'required' => ['description'], 'additionalProperties' => false], $model);
        } catch (\Throwable $exception) {
            $usage->fail($reserved);

            return $this->providerError($exception);
        }

        $final = $usage->complete($reserved, $result['usage'], $calculator, $model);

        return $this->generationResponse($result['result'], $final, $usage);
    }

    public function breakdown(AiBreakdownRequest $request, Board $board, AiModelRouter $router, OpenAiClient $client, AiUsageService $usage, AiCreditCalculator $calculator, AiProjectContextBuilder $context, AiProductContext $productContext): JsonResponse
    {
        $response = $this->generateProject($request, $board, 'breakdown', ['tasks' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'priority' => ['type' => ['string', 'null'], 'enum' => ['low', 'medium', 'high', null]]], 'required' => ['title', 'description', 'priority'], 'additionalProperties' => false]]], $router, $client, $usage, $calculator, $context, $productContext, ['objective' => $request->validated('objective'), 'desired_count' => $request->validated('desired_count')]);
        $data = $response->getData(true);
        $data['data']['tasks'] = array_slice($data['data']['tasks'] ?? [], 0, (int) $request->validated('desired_count'));

        return response()->json($data);
    }

    public function summary(AiProjectRequest $request, Board $board, AiModelRouter $router, OpenAiClient $client, AiUsageService $usage, AiCreditCalculator $calculator, AiProjectContextBuilder $context, AiProductContext $productContext): JsonResponse
    {
        return $this->generateProject($request, $board, 'summary', ['overview' => ['type' => 'string'], 'highlights' => ['type' => 'array', 'items' => ['type' => 'string']], 'attention_items' => ['type' => 'array', 'items' => ['type' => 'string']]], $router, $client, $usage, $calculator, $context, $productContext);
    }

    public function analysis(AiProjectRequest $request, Board $board, AiModelRouter $router, OpenAiClient $client, AiUsageService $usage, AiCreditCalculator $calculator, AiProjectContextBuilder $context, AiProductContext $productContext): JsonResponse
    {
        $response = $this->generateProject($request, $board, 'analysis', ['executive_summary' => ['type' => 'string'], 'risks' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']], 'title' => ['type' => 'string'], 'detail' => ['type' => 'string'], 'task_ids' => ['type' => 'array', 'items' => ['type' => 'integer']]], 'required' => ['severity', 'title', 'detail', 'task_ids'], 'additionalProperties' => false]], 'priorities' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'reason' => ['type' => 'string'], 'task_ids' => ['type' => 'array', 'items' => ['type' => 'integer']]], 'required' => ['title', 'reason', 'task_ids'], 'additionalProperties' => false]], 'recommendations' => ['type' => 'array', 'items' => ['type' => 'string']]], $router, $client, $usage, $calculator, $context, $productContext);
        $data = $response->getData(true);
        $contextIds = collect($context->build($board)['tasks'] ?? [])->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $allowedIds = $board->tasks()->whereIn('id', $contextIds)->pluck('id')->map(fn ($id): int => (int) $id)->flip()->all();

        foreach (['risks', 'priorities'] as $key) {
            foreach ($data['data'][$key] ?? [] as $index => $item) {
                $data['data'][$key][$index]['task_ids'] = array_values(array_filter(array_map('intval', $item['task_ids'] ?? []), fn (int $id): bool => isset($allowedIds[$id])));
            }
        }

        return response()->json($data);
    }

    public function applyBreakdown(ApplyAiTaskBreakdownRequest $request, Board $board, ApplyAiTaskBreakdown $action, CreateTask $createTask): JsonResponse
    {
        abort_unless($board->workspace->canEditContent($request->user()), 403);
        $tasks = $action->execute($request->user(), $board, (int) $request->validated('column_id'), $request->validated('tasks'), $createTask);

        return response()->json(['data' => $tasks], 201);
    }

    private function generateProject(Request $request, Board $board, string $feature, array $schema, AiModelRouter $router, OpenAiClient $client, AiUsageService $usage, AiCreditCalculator $calculator, AiProjectContextBuilder $context, AiProductContext $productContext, array $extra = []): JsonResponse
    {
        abort_unless($board->workspace->canEditContent($request->user()), 403);
        $model = $router->resolve($request->validated('reasoning_level'));
        $input = json_encode(['context' => $context->build($board), ...$extra], JSON_THROW_ON_ERROR);
        $reserved = $usage->reserve($board, $request->user()->id, $request->validated('request_id'), $feature, $request->validated('reasoning_level'), $model, strlen($input), 1200);

        try {
            $instructions = ['breakdown' => 'Scomponi l’obiettivo in attività realizzabili e separate usando esclusivamente concetti supportati dal prodotto.', 'summary' => 'Riassumi lo stato corrente del progetto in modo descrittivo.', 'analysis' => 'Analizza rischi, priorità e possibili miglioramenti usando esclusivamente i dati disponibili. Distingui fatti da inferenze.'];
            $result = $client->generate([['role' => 'system', 'content' => $productContext->systemPrompt($instructions[$feature] ?? 'Analizza i dati disponibili senza inventare fatti.')], ['role' => 'user', 'content' => $input]], ['type' => 'object', 'properties' => $schema, 'required' => array_keys($schema), 'additionalProperties' => false], $model);
        } catch (\Throwable $exception) {
            $usage->fail($reserved);

            return $this->providerError($exception);
        }

        $final = $usage->complete($reserved, $result['usage'], $calculator, $model);

        return $this->generationResponse($result['result'], $final, $usage);
    }

    private function generationResponse(array $data, AiUsageLog $usageLog, AiUsageService $usage): JsonResponse
    {
        return response()->json(['data' => $data, 'usage' => ['credits_used' => $usageLog->credits_used, 'remaining_credits' => $usage->remaining($usageLog)]]);
    }

    private function providerError(\Throwable $exception): JsonResponse
    {
        $code = $exception->getMessage();

        $allowed = [
            'ai_provider_not_configured',
            'ai_provider_unavailable',
            'ai_provider_error',
            'ai_provider_invalid_response',
            'ai_provider_refused',
            'ai_provider_incomplete',
        ];

        if (! in_array($code, $allowed, true)) {
            throw $exception;
        }

        return response()->json(['code' => $code], 502);
    }
}
