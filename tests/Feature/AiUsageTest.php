<?php

namespace Tests\Feature;

use App\Models\AiUsageLog;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiUsageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Board $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        $this->owner = User::factory()->create();
        $plan = Plan::where('slug', 'team')->firstOrFail();
        $plan->update(['ai_enabled' => true, 'ai_monthly_credits' => 100]);
        $this->owner->subscription()->update(['plan_id' => $plan->id]);
        $workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'AI Team', 'type' => 'shared']);
        $workspace->members()->attach($this->owner->id, ['role' => 'owner', 'joined_at' => now()]);
        $this->board = Board::create(['workspace_id' => $workspace->id, 'name' => 'AI']);
        BoardColumn::create(['board_id' => $this->board->id, 'name' => 'To do', 'position' => 1000]);
        config(['ai.api_key' => 'test-key']);
    }

    public function test_status_and_success_use_the_finalized_usage(): void
    {
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['overview' => 'ok', 'highlights' => [], 'attention_items' => []])]]]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 100]], 200)]);
        $requestId = (string) Str::uuid();

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", ['request_id' => $requestId, 'reasoning_level' => 'medium'])->assertOk();

        $this->assertDatabaseHas('ai_usage_logs', ['request_id' => $requestId, 'status' => 'completed', 'reserved_credits' => 0]);
        $this->actingAs($this->owner)->getJson("/api/boards/{$this->board->id}/ai/status")->assertOk()->assertJsonPath('reserved_credits', 0)->assertJsonPath('used_credits', 2);
    }

    public function test_duplicate_request_id_is_rejected_without_second_provider_call(): void
    {
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['overview' => 'ok', 'highlights' => [], 'attention_items' => []])]]]], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]], 200)]);
        $requestId = (string) Str::uuid();
        $payload = ['request_id' => $requestId, 'reasoning_level' => 'low'];

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", $payload)->assertOk();
        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", $payload)->assertStatus(409)->assertJsonPath('code', 'ai_request_already_processed');
        Http::assertSentCount(1);
    }

    public function test_provider_failure_releases_reservation(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'high'])->assertStatus(502);

        $usage = AiUsageLog::firstOrFail();
        $this->assertSame('failed', $usage->status);
        $this->assertSame(0, $usage->credits_used);
        $this->assertSame(0, $usage->reserved_credits);
    }

    public function test_viewer_and_outsider_are_denied(): void
    {
        $viewer = User::factory()->create();
        $this->board->workspace->members()->attach($viewer->id, ['role' => 'viewer', 'joined_at' => now()]);
        $outsider = User::factory()->create();
        $payload = ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'low'];

        $this->actingAs($viewer)->postJson("/api/boards/{$this->board->id}/ai/summary", $payload)->assertForbidden();
        $this->actingAs($outsider)->postJson("/api/boards/{$this->board->id}/ai/summary", ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'low'])->assertForbidden();
    }
}
