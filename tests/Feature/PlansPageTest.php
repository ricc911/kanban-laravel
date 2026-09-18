<?php

namespace Tests\Feature;

use App\Http\Controllers\PlanController;
use App\Models\AiUsageLog;
use App\Models\Board;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\AiBillingPeriodResolver;
use App\Services\Plans\PlanLimitService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlansPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_plans_page_requires_authentication(): void
    {
        $this->get('/plans')->assertRedirect();
    }

    public function test_page_shows_public_catalog_current_plan_and_usage(): void
    {
        $user = User::factory()->create();
        $user->subscription()->update(['plan_id' => Plan::where('slug', 'team')->value('id')]);
        $workspace = $user->ownedWorkspaces()->firstOrFail();
        Board::create(['workspace_id' => $workspace->id, 'name' => 'Progetto uno']);
        Board::create(['workspace_id' => $workspace->id, 'name' => 'Progetto due']);
        $shared = Workspace::create(['owner_id' => $user->id, 'name' => 'Team condiviso', 'type' => 'shared']);
        $shared->members()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
        [$start, $end] = app(AiBillingPeriodResolver::class)->resolve();
        AiUsageLog::create(['request_id' => (string) Str::uuid(), 'subscription_id' => $user->subscription->id, 'workspace_id' => $workspace->id, 'user_id' => $user->id, 'feature' => 'summary', 'reasoning_level' => 'medium', 'model' => 'test', 'status' => 'completed', 'reserved_credits' => 0, 'credits_used' => 125, 'period_start' => $start, 'period_end' => $end, 'expires_at' => now(), 'completed_at' => now()]);

        $response = $this->actingAs($user)->get('/plans');

        $response->assertOk()->assertViewIs('plans.index')->assertViewHas('catalog', fn ($catalog) => $catalog->count() === 4 && ! $catalog->contains('slug', 'unlimited'));
        $response->assertSee('Team')->assertSee('Piani e utilizzo')->assertSee('2 / 50')->assertSee('1 / 3')->assertSee('2.875 disponibili su 3.000');
    }

    public function test_unlimited_is_only_shown_as_current_plan_and_unlimited_values_are_user_facing(): void
    {
        $user = User::factory()->create();
        $user->subscription()->update(['plan_id' => Plan::where('slug', 'unlimited')->value('id')]);

        $response = $this->actingAs($user)->get('/plans');

        $response->assertOk()->assertSee('Unlimited')->assertSee('Illimitati')->assertViewHas('catalog', fn ($catalog) => $catalog->count() === 4 && ! $catalog->contains('name', 'Unlimited'));
    }

    public function test_unlimited_with_null_price_is_rendered_as_internal_plan(): void
    {
        $user = User::factory()->create();
        $unlimited = Plan::where('slug', 'unlimited')->firstOrFail();
        $user->subscription()->update(['plan_id' => $unlimited->id]);

        $response = $this->actingAs($user)->get('/plans');

        $response->assertOk()->assertSee('Unlimited')->assertSee('Piano interno')->assertViewHas('currentPrice', 'Piano interno');
        $response->assertViewHas('catalog', fn ($catalog) => $catalog->count() === 4 && ! $catalog->contains('slug', 'unlimited'));

        $method = new \ReflectionMethod(app(PlanController::class), 'formatPrice');
        $method->setAccessible(true);
        $this->assertSame('Piano interno', $method->invoke(app(PlanController::class), null));
    }

    public function test_over_limit_usage_is_rendered_without_mutating_subscription(): void
    {
        $user = User::factory()->create();
        $plan = Plan::where('slug', 'pro')->firstOrFail();
        $user->subscription()->update(['plan_id' => $plan->id]);
        $workspace = $user->ownedWorkspaces()->firstOrFail();
        for ($number = 1; $number <= 12; $number++) {
            Board::create(['workspace_id' => $workspace->id, 'name' => "Project {$number}"]);
        }

        $this->actingAs($user)->get('/plans')->assertOk()->assertSee('12 / 10')->assertSee('Sei oltre il limite del piano');
        $this->assertSame($plan->id, $user->subscription->fresh()->plan_id);
        $this->assertSame(12, app(PlanLimitService::class)->ownedProjectCount($user));
    }
}
