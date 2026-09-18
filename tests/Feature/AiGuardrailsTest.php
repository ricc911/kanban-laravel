<?php

namespace Tests\Feature;

use App\Events\TaskCreated;
use App\Models\AiUsageLog;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiGuardrailsTest extends TestCase
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
        $workspace = Workspace::create(['owner_id' => $this->owner->id, 'name' => 'AI Team', 'type' => 'shared']);
        $workspace->members()->attach($this->owner->id, ['role' => 'owner', 'joined_at' => now()]);
        $this->board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Board A', 'description' => 'Board A context']);
        $this->column = BoardColumn::create(['board_id' => $this->board->id, 'name' => 'Backlog', 'position' => 1000]);
        config(['ai.api_key' => 'test-key']);
    }

    public function test_viewer_can_view_board_but_cannot_use_any_ai_mutation_or_generation(): void
    {
        $viewer = User::factory()->create();
        $this->board->workspace->members()->attach($viewer->id, ['role' => 'viewer', 'joined_at' => now()]);
        $task = $this->createTask('Task viewer');
        Http::fake();

        $this->actingAs($viewer)->getJson("/api/boards/{$this->board->id}")->assertOk();
        $this->actingAs($viewer)->postJson("/api/tasks/{$task->id}/ai/generate-description", $this->descriptionPayload())->assertForbidden();
        $this->actingAs($viewer)->postJson("/api/boards/{$this->board->id}/ai/breakdown", $this->breakdownPayload())->assertForbidden();
        $this->actingAs($viewer)->postJson("/api/boards/{$this->board->id}/ai/summary", $this->projectPayload())->assertForbidden();
        $this->actingAs($viewer)->postJson("/api/boards/{$this->board->id}/ai/analysis", $this->projectPayload())->assertForbidden();
        $this->actingAs($viewer)->postJson("/api/boards/{$this->board->id}/ai/breakdown/apply", ['column_id' => $this->column->id, 'tasks' => [['title' => 'Nope']]])->assertForbidden();

        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_usage_logs', 0);
    }

    public function test_owner_admin_and_member_can_use_ai_and_shared_users_use_owner_subscription(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $this->board->workspace;
        $workspace->members()->attach($admin->id, ['role' => 'admin', 'joined_at' => now()]);
        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        Http::fake(['*' => Http::response($this->providerResponse(['overview' => 'ok', 'highlights' => [], 'attention_items' => []]), 200)]);

        foreach ([$this->owner, $admin, $member] as $user) {
            $this->actingAs($user)->postJson("/api/boards/{$this->board->id}/ai/summary", $this->projectPayload())->assertOk();
        }

        $this->assertSame(3, AiUsageLog::count());
        $this->assertSame($this->owner->subscription->id, AiUsageLog::where('user_id', $admin->id)->firstOrFail()->subscription_id);
        $this->assertSame($this->owner->subscription->id, AiUsageLog::where('user_id', $member->id)->firstOrFail()->subscription_id);
        Http::assertSentCount(3);
    }

    public function test_disabled_ai_is_rejected_before_the_provider(): void
    {
        $this->owner->subscription->plan->update(['ai_enabled' => false]);
        Http::fake();

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", $this->projectPayload())
            ->assertForbidden()->assertJsonPath('code', 'ai_disabled');

        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_usage_logs', 0);
    }

    public function test_exhausted_credits_reject_generation_and_status_never_goes_negative(): void
    {
        $this->createCompletedUsage(10000);
        Http::fake();

        $this->actingAs($this->owner)->getJson("/api/boards/{$this->board->id}/ai/status")->assertOk()->assertJsonPath('remaining_credits', 0);
        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", $this->projectPayload())
            ->assertStatus(429)->assertJsonPath('code', 'ai_credits_exhausted');

        Http::assertNothingSent();
        $this->assertDatabaseMissing('ai_usage_logs', ['status' => 'pending']);
    }

    public function test_insufficient_credits_for_reservation_is_rejected_before_provider(): void
    {
        $this->owner->subscription->plan->update(['ai_monthly_credits' => 1]);
        Http::fake();

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", $this->projectPayload())
            ->assertStatus(429)->assertJsonPath('code', 'ai_credits_exhausted');

        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_usage_logs', 0);
    }

    public function test_http_provider_failure_releases_reservation_without_consumption(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'temporary']], 503)]);

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", $this->projectPayload())->assertStatus(502);

        $this->assertDatabaseHas('ai_usage_logs', ['status' => 'failed', 'reserved_credits' => 0, 'credits_used' => 0]);
        $this->actingAs($this->owner)->getJson("/api/boards/{$this->board->id}/ai/status")->assertJsonPath('remaining_credits', 10000);
    }

    public function test_malformed_provider_response_releases_reservation(): void
    {
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['type' => 'reasoning']]], 200)]);

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", $this->projectPayload())
            ->assertStatus(502)->assertJsonPath('code', 'ai_provider_invalid_response');
        $this->assertFailedUsageReleased();
    }

    public function test_invalid_json_provider_response_releases_reservation(): void
    {
        Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{invalid']]]]], 200)]);

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", $this->projectPayload())
            ->assertStatus(502)->assertJsonPath('code', 'ai_provider_invalid_response');
        $this->assertFailedUsageReleased();
    }

    public function test_refusal_and_incomplete_provider_responses_are_not_successes(): void
    {
        Http::fakeSequence()
            ->push(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'no']]]]], 200)
            ->push(['status' => 'incomplete', 'output' => []], 200);

        foreach (['ai_provider_refused', 'ai_provider_incomplete'] as $code) {
            $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", $this->projectPayload())
                ->assertStatus(502)->assertJsonPath('code', $code);
        }

        $this->assertSame(2, AiUsageLog::where('status', 'failed')->count());
        $this->assertSame(0, (int) AiUsageLog::sum('credits_used'));
        $this->assertSame(0, (int) AiUsageLog::where('status', 'pending')->sum('reserved_credits'));
    }

    public function test_duplicate_request_id_is_rejected_without_second_provider_call(): void
    {
        Http::fake(['*' => Http::response($this->providerResponse(['overview' => 'ok', 'highlights' => [], 'attention_items' => []]), 200)]);
        $payload = $this->projectPayload();

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", $payload)->assertOk();
        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", $payload)
            ->assertStatus(409)->assertJsonPath('code', 'ai_request_already_processed');

        Http::assertSentCount(1);
        $this->assertDatabaseCount('ai_usage_logs', 1);
    }

    public function test_apply_breakdown_does_not_consume_ai_or_call_provider(): void
    {
        Event::fake([TaskCreated::class]);
        Http::fake(['*' => Http::response($this->providerResponse(['tasks' => [['title' => 'Proposta', 'description' => 'Test', 'priority' => 'medium']]]), 200)]);
        $before = Task::where('board_id', $this->board->id)->count();
        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/breakdown", $this->breakdownPayload())->assertOk();
        $logsBeforeApply = AiUsageLog::count();

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/breakdown/apply", ['column_id' => $this->column->id, 'tasks' => [['title' => 'Solo questa', 'description' => 'Descrizione', 'priority' => 'high']]])->assertCreated();

        $this->assertSame($before + 1, Task::where('board_id', $this->board->id)->count());
        $this->assertSame($logsBeforeApply, AiUsageLog::count());
        Http::assertSentCount(1);
    }

    public function test_rate_limit_blocks_generation_without_provider_or_credit_usage(): void
    {
        RateLimiter::clear((string) $this->owner->id);
        Http::fake(['*' => Http::response($this->providerResponse(['overview' => 'ok', 'highlights' => [], 'attention_items' => []]), 200)]);

        foreach (range(1, 10) as $attempt) {
            $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", $this->projectPayload())->assertOk();
        }
        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", $this->projectPayload())->assertStatus(429);

        Http::assertSentCount(10);
        $this->assertSame(10, AiUsageLog::count());
    }

    public function test_user_cannot_use_ai_for_a_board_without_access(): void
    {
        $other = User::factory()->create();
        $otherBoard = Board::create(['workspace_id' => $other->ownedWorkspaces()->firstOrFail()->id, 'name' => 'Board B', 'description' => 'Private B']);
        BoardColumn::create(['board_id' => $otherBoard->id, 'name' => 'B column', 'position' => 1000]);
        Http::fake();

        $this->actingAs($this->owner)->getJson("/api/boards/{$otherBoard->id}/ai/status")->assertForbidden();
        $this->actingAs($this->owner)->postJson("/api/boards/{$otherBoard->id}/ai/summary", $this->projectPayload())->assertForbidden();

        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_usage_logs', 0);
    }

    public function test_generation_rate_limit_does_not_affect_apply_breakdown(): void
    {
        RateLimiter::clear((string) $this->owner->id);
        Event::fake([TaskCreated::class]);
        Http::fake(['*' => Http::response($this->providerResponse(['overview' => 'ok', 'highlights' => [], 'attention_items' => []]), 200)]);
        foreach (range(1, 10) as $attempt) {
            $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/summary", $this->projectPayload())->assertOk();
        }

        $this->actingAs($this->owner)->postJson("/api/boards/{$this->board->id}/ai/breakdown/apply", ['column_id' => $this->column->id, 'tasks' => [['title' => 'Applicata']]])->assertCreated();
        Http::assertSentCount(10);
    }

    private function createTask(string $title, ?string $priority = null): Task
    {
        return Task::create(['board_id' => $this->board->id, 'board_column_id' => $this->column->id, 'title' => $title, 'position' => 1000, 'priority' => $priority]);
    }

    private function projectPayload(): array
    {
        return ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'medium'];
    }

    private function descriptionPayload(): array
    {
        return ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'low'];
    }

    private function breakdownPayload(): array
    {
        return ['request_id' => (string) Str::uuid(), 'reasoning_level' => 'medium', 'objective' => 'Obiettivo', 'desired_count' => 3];
    }

    private function providerResponse(array $result): array
    {
        return ['status' => 'completed', 'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => json_encode($result, JSON_THROW_ON_ERROR)]]]], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]];
    }

    private function createCompletedUsage(int $credits): AiUsageLog
    {
        $start = now()->startOfMonth();

        return AiUsageLog::create(['request_id' => (string) Str::uuid(), 'subscription_id' => $this->owner->subscription->id, 'workspace_id' => $this->board->workspace_id, 'user_id' => $this->owner->id, 'feature' => 'summary', 'reasoning_level' => 'medium', 'model' => config('ai.models.medium.model'), 'status' => 'completed', 'reserved_credits' => 0, 'credits_used' => $credits, 'period_start' => $start, 'period_end' => $start->copy()->addMonth()->subSecond(), 'expires_at' => now(), 'completed_at' => now()]);
    }

    private function assertFailedUsageReleased(): void
    {
        $this->assertDatabaseHas('ai_usage_logs', ['status' => 'failed', 'reserved_credits' => 0, 'credits_used' => 0]);
        $this->assertDatabaseMissing('ai_usage_logs', ['status' => 'pending']);
    }
}
