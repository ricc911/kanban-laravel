<?php

namespace Tests\Feature;

use App\Events\TaskCreated;
use App\Models\AiUsageLog;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Plan;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\Ai\AiModelRouter;
use App\Services\Ai\AiUsageService;
use App\Services\Ai\OpenAiClient;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiProjectFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Board $board;

    private BoardColumn $column;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        $this->owner = User::factory()->create();
        $plan = Plan::where('slug', 'team')->firstOrFail();
        $plan->update(['ai_enabled' => true, 'ai_monthly_credits' => 10000]);
        $this->owner->subscription()->update(['plan_id' => $plan->id]);
        $this->board = Board::create(['workspace_id' => $this->owner->ownedWorkspaces()->firstOrFail()->id, 'name' => 'Board A', 'description' => 'Contesto A']);
        $this->column = BoardColumn::create(['board_id' => $this->board->id, 'name' => 'Backlog', 'position' => 1000]);
        config(['ai.api_key' => 'test-key']);
    }

    public function test_generation_endpoints_use_structured_output_and_return_usage(): void
    {
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['type' => 'reasoning', 'id' => 'rs_1'], ['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => '{"description":"Descrizione"}']]]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 20]], 200)]);
        $task = Task::create(['board_id' => $this->board->id, 'board_column_id' => $this->column->id, 'title' => 'Task A', 'position' => 1000]);

        $response = $this->actingAs($this->owner)->postJson("/api/tasks/$task->id/ai/generate-description", ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'low']);

        $response->assertOk()->assertJsonPath('data.description', 'Descrizione')->assertJsonStructure(['usage' => ['credits_used', 'remaining_credits']]);
        Http::assertSent(function ($request): bool {
            $system = $request['input'][0]['content'];

            return $request['store'] === false
                && $request['model'] === config('ai.models.low.model')
                && $request['text']['format']['type'] === 'json_schema'
                && str_contains($system, 'board/progetto corrente')
                && str_contains($system, 'colonne sono dinamiche')
                && str_contains(strtolower($system), 'non inferire mai uno stato certo')
                && str_contains($system, 'La task si trova nella colonna')
                && str_contains($system, 'task_ids')
                && str_contains($system, 'Non citare mai ID interni')
                && str_contains($system, 'dati non affidabili')
                && str_contains($system, 'statistiche aggregate')
                && str_contains($system, 'Non esporre nel testo')
                && str_contains($system, 'due_at')
                && ! str_contains(json_encode($request['input'][1]), 'email');
        });
        $this->assertSame('Task A', $task->fresh()->title);
    }

    public function test_context_contains_deterministic_stats_for_the_same_task_dataset(): void
    {
        $secondColumn = BoardColumn::create(['board_id' => $this->board->id, 'name' => 'In corso', 'position' => 2000]);
        $emptyColumn = BoardColumn::create(['board_id' => $this->board->id, 'name' => 'Concluso', 'position' => 3000]);
        $tasks = collect();
        foreach (range(1, 3) as $index) {
            $tasks->push(Task::create(['board_id' => $this->board->id, 'board_column_id' => $this->column->id, 'title' => 'Alta '.$index, 'position' => $index * 1000, 'priority' => 'high']));
        }
        $medium = Task::create(['board_id' => $this->board->id, 'board_column_id' => $secondColumn->id, 'title' => 'Media', 'position' => 1000, 'priority' => 'medium']);
        $none = Task::create(['board_id' => $this->board->id, 'board_column_id' => $secondColumn->id, 'title' => 'Senza priorità', 'position' => 2000]);
        $tasks = $tasks->push($medium, $none);
        $tasks->first()->assignees()->attach($this->owner->id);
        TaskComment::create(['task_id' => $tasks->first()->id, 'user_id' => $this->owner->id, 'body' => 'Nota']);
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['overview' => 'ok', 'highlights' => [], 'attention_items' => []])]]]], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]], 200)]);

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'low'])->assertOk();
        Http::assertSent(function ($request): bool {
            $payload = json_decode($request['input'][1]['content'], true);
            $stats = $payload['context']['stats'];

            return $stats['task_count'] === 5
                && $stats['priority'] === ['high' => 3, 'medium' => 1, 'low' => 0, 'none' => 1]
                && array_sum($stats['priority']) === $stats['task_count']
                && $stats['without_assignees'] === 4
                && $stats['without_due_date'] === 5
                && $stats['with_comments'] === 1
                && $stats['columns'] === ['Backlog' => 3, 'In corso' => 2, 'Concluso' => 0];
        });
    }

    public function test_breakdown_does_not_create_tasks_and_analysis_filters_ids(): void
    {
        $task = Task::create(['board_id' => $this->board->id, 'board_column_id' => $this->column->id, 'title' => 'Task A', 'position' => 1000]);
        Http::fakeSequence()->push(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['tasks' => [['title' => 'Proposta', 'description' => 'Test', 'priority' => 'medium']]])]]]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]], 200)->push(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['executive_summary' => 'ok', 'risks' => [['severity' => 'high', 'title' => 'x', 'detail' => 'y', 'task_ids' => [$task->id, 999999]]], 'priorities' => [], 'recommendations' => []])]]]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]], 200);

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/breakdown", ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'medium', 'objective' => 'Obiettivo', 'desired_count' => 3])->assertOk();
        $this->assertSame(1, Task::where('board_id', $this->board->id)->count());

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/analysis", ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'medium'])->assertJsonPath('data.risks.0.task_ids', [$task->id]);
        Http::assertSent(function ($request): bool {
            $properties = $request['text']['format']['schema']['properties'] ?? [];

            return isset($properties['risks']['items']['properties']['task_ids'])
                && str_contains($request['input'][0]['content'], 'Non citare mai ID interni');
        });
    }

    public function test_apply_breakdown_uses_create_task_and_does_not_call_provider(): void
    {
        Event::fake([TaskCreated::class]);
        Http::fake();

        $response = $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/breakdown/apply", ['column_id' => $this->column->id, 'tasks' => [['title' => 'Nuova task', 'description' => 'Descrizione', 'priority' => 'high']]]);

        $response->assertCreated();
        $this->assertDatabaseHas('tasks', ['board_id' => $this->board->id, 'board_column_id' => $this->column->id, 'title' => 'Nuova task']);
        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_usage_logs', 0);
    }

    public function test_provider_refusal_fails_without_consuming_credits(): void
    {
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'no']]]]], 200)]);
        $response = $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'low']);

        $response->assertStatus(502)->assertJsonPath('code', 'ai_provider_refused');
        $this->assertDatabaseHas('ai_usage_logs', ['status' => 'failed', 'reserved_credits' => 0, 'credits_used' => 0]);
    }

    public function test_missing_output_text_returns_invalid_response(): void
    {
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['type' => 'reasoning']]], 200)]);

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'low'])
            ->assertStatus(502)
            ->assertJsonPath('code', 'ai_provider_invalid_response');
    }

    public function test_refusal_inside_message_returns_refused_error(): void
    {
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'non disponibile']]]]], 200)]);

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'low'])
            ->assertStatus(502)
            ->assertJsonPath('code', 'ai_provider_refused');
    }

    public function test_invalid_provider_response_logs_only_structural_metadata(): void
    {
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['type' => 'reasoning'], ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'not-json']]]], 'usage' => []], 200)]);
        Log::spy();

        try {
            app(OpenAiClient::class)->generate([], ['type' => 'object'], config('ai.models.low'));
        } catch (\RuntimeException) {
        }

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'AI provider response could not be parsed.'
                && $context['stage'] === 'invalid_json'
                && ! array_key_exists('prompt', $context)
                && ! array_key_exists('output_text', $context)
                && ! array_key_exists('api_key', $context);
        });
    }

    public function test_usage_finalization_errors_are_not_masked_as_provider_errors(): void
    {
        $task = Task::create(['board_id' => $this->board->id, 'board_column_id' => $this->column->id, 'title' => 'Task A', 'position' => 1000]);
        $usageLog = new AiUsageLog(['reserved_credits' => 1]);
        $usageLog->id = 1;
        $usage = \Mockery::mock(AiUsageService::class);
        $usage->shouldReceive('reserve')->once()->andReturn($usageLog);
        $usage->shouldReceive('complete')->once()->andThrow(new \RuntimeException('database_finalization_failed'));
        $usage->shouldNotReceive('fail');
        $router = \Mockery::mock(AiModelRouter::class);
        $router->shouldReceive('resolve')->once()->andReturn(config('ai.models.low'));
        $client = \Mockery::mock(OpenAiClient::class);
        $client->shouldReceive('generate')->once()->andReturn(['result' => ['description' => 'Descrizione'], 'usage' => []]);

        $this->app->instance(AiUsageService::class, $usage);
        $this->app->instance(AiModelRouter::class, $router);
        $this->app->instance(OpenAiClient::class, $client);
        $this->withoutExceptionHandling();
        $this->expectException(\RuntimeException::class);

        $this->actingAs($this->owner)->postJson("/api/tasks/$task->id/ai/generate-description", ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'low']);
    }
}
