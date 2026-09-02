<?php

namespace Tests\Feature;

use App\Events\TaskCreated;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
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
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['content' => [['text' => json_encode(['description' => 'Descrizione'])]]]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 20]], 200)]);
        $task = Task::create(['board_id' => $this->board->id, 'board_column_id' => $this->column->id, 'title' => 'Task A', 'position' => 1000]);

        $response = $this->actingAs($this->owner)->postJson("/api/tasks/$task->id/ai/generate-description", ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'low']);

        $response->assertOk()->assertJsonPath('data.description', 'Descrizione')->assertJsonStructure(['usage' => ['credits_used', 'remaining_credits']]);
        Http::assertSent(fn ($request): bool => $request['store'] === false && $request['model'] === config('ai.models.low.model') && $request['text']['format']['type'] === 'json_schema' && ! str_contains($request->body(), 'email'));
        $this->assertSame('Task A', $task->fresh()->title);
    }

    public function test_breakdown_does_not_create_tasks_and_analysis_filters_ids(): void
    {
        $task = Task::create(['board_id' => $this->board->id, 'board_column_id' => $this->column->id, 'title' => 'Task A', 'position' => 1000]);
        Http::fakeSequence()->push(['status' => 'completed', 'output' => [['content' => [['text' => json_encode(['tasks' => [['title' => 'Proposta', 'description' => 'Test', 'priority' => 'medium']]])]]]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]], 200)->push(['status' => 'completed', 'output' => [['content' => [['text' => json_encode(['executive_summary' => 'ok', 'risks' => [['severity' => 'high', 'title' => 'x', 'detail' => 'y', 'task_ids' => [$task->id, 999999]]], 'priorities' => [], 'recommendations' => []])]]]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]], 200);

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/breakdown", ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'medium', 'objective' => 'Obiettivo', 'desired_count' => 3])->assertOk();
        $this->assertSame(1, Task::where('board_id', $this->board->id)->count());

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/analysis", ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'medium'])->assertJsonPath('data.risks.0.task_ids', [$task->id]);
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
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'refusal', 'refusal' => 'no']]]]], 200)]);
        $response = $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'low']);

        $response->assertStatus(502)->assertJsonPath('code', 'ai_provider_refused');
        $this->assertDatabaseHas('ai_usage_logs', ['status' => 'failed', 'reserved_credits' => 0, 'credits_used' => 0]);
    }
}
