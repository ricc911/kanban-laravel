<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\Plans\PlanLimitService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_catalog_contains_the_five_idempotent_plans_with_final_values(): void
    {
        $this->seed(PlanSeeder::class);
        $this->assertSame(5, Plan::count());

        $this->assertPlan('free', 0, 1, 0, 1, false, 0);
        $this->assertPlan('pro', 499, 10, 0, 1, true, 500);
        $this->assertPlan('team', 1499, 50, 3, 10, true, 3000);
        $this->assertPlan('business', 3999, null, 10, 40, true, 10000);
        $this->assertPlan('unlimited', 0, null, null, null, true, 1000000);
    }

    public function test_free_can_create_one_project_but_cannot_create_a_shared_workspace_through_the_api(): void
    {
        $owner = $this->userOnPlan('free');
        $personal = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();

        $this->createBoard($owner, $personal)->assertCreated();
        $this->createBoard($owner, $personal)->assertUnprocessable();
        $this->actingAs($owner)->postJson('/api/workspaces', ['name' => 'Team'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('workspace')
            ->assertJsonPath('errors.workspace.0', 'Il tuo piano non consente di creare altri workspace condivisi.');

        $this->assertSame(1, $owner->ownedWorkspaces()->where('type', 'personal')->count());
        $this->assertSame(0, $owner->ownedWorkspaces()->where('type', 'shared')->count());
        $this->assertSame(1, $personal->members()->count());
    }

    public function test_pro_project_limit_is_independent_of_shared_workspace_limit(): void
    {
        $owner = $this->userOnPlan('pro');
        $personal = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();

        for ($number = 1; $number <= 10; $number++) {
            $this->createBoard($owner, $personal)->assertCreated();
        }

        $this->createBoard($owner, $personal)->assertUnprocessable();
        $this->actingAs($owner)->postJson('/api/workspaces', ['name' => 'Team'])->assertUnprocessable();
        $this->assertSame(10, app(PlanLimitService::class)->ownedProjectCount($owner));
        $this->assertSame(0, app(PlanLimitService::class)->ownedSharedWorkspaceCount($owner));
    }

    public function test_member_board_creation_consumes_the_owner_project_limit_across_workspaces(): void
    {
        $owner = $this->userOnPlan('team');
        $owner->subscription->plan->update(['max_projects' => 1]);
        $personal = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $shared = $this->createShared($owner);
        $member = User::factory()->create();
        $shared->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($member)->postJson("/api/workspaces/{$shared->id}/boards", ['name' => 'Shared project'])->assertCreated();
        $this->actingAs($owner)->postJson("/api/workspaces/{$personal->id}/boards", ['name' => 'Extra project'])->assertUnprocessable();

        $this->assertSame(1, app(PlanLimitService::class)->ownedProjectCount($owner));
        $this->assertDatabaseMissing('boards', ['workspace_id' => $personal->id, 'name' => 'Extra project']);
    }

    public function test_team_can_create_three_owner_only_shared_workspaces_but_not_a_fourth(): void
    {
        $owner = $this->userOnPlan('team');
        $limits = app(PlanLimitService::class);
        $this->assertSame(0, $limits->ownedSharedWorkspaceCount($owner));

        for ($number = 1; $number <= 3; $number++) {
            $response = $this->actingAs($owner)->postJson('/api/workspaces', ['name' => "Team {$number}"])
                ->assertCreated()
                ->assertJsonPath('data.type', 'shared');
            $workspace = Workspace::findOrFail($response->json('data.id'));
            $this->assertSame(1, $workspace->members()->count());
            $this->assertSame($number, $limits->ownedSharedWorkspaceCount($owner));
        }

        $this->actingAs($owner)->postJson('/api/workspaces', ['name' => 'Team 4'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.workspace.0', 'Hai raggiunto il limite di workspace condivisi del tuo piano.');

        $this->assertSame(3, $owner->ownedWorkspaces()->where('type', 'shared')->count());
        $this->assertSame(1, $owner->ownedWorkspaces()->where('type', 'personal')->count());
        $this->assertFalse($limits->canCreateSharedWorkspace($owner));
    }

    public function test_unlimited_plan_can_create_more_than_ten_shared_workspaces(): void
    {
        $owner = $this->userOnPlan('unlimited');

        for ($number = 1; $number <= 11; $number++) {
            $this->actingAs($owner)->postJson('/api/workspaces', ['name' => "Team {$number}"])->assertCreated();
        }

        $this->assertSame(11, app(PlanLimitService::class)->ownedSharedWorkspaceCount($owner));
        $this->assertTrue(app(PlanLimitService::class)->canCreateSharedWorkspace($owner));
        $this->assertSame(1, $owner->ownedWorkspaces()->where('type', 'personal')->count());
    }

    public function test_business_can_create_ten_shared_workspaces_but_not_an_eleventh(): void
    {
        $owner = $this->userOnPlan('business');

        for ($number = 1; $number <= 10; $number++) {
            $this->actingAs($owner)->postJson('/api/workspaces', ['name' => "Team {$number}"])->assertCreated();
        }

        $this->actingAs($owner)->postJson('/api/workspaces', ['name' => 'Team 11'])->assertUnprocessable();

        $this->assertSame(10, app(PlanLimitService::class)->ownedSharedWorkspaceCount($owner));
    }

    public function test_existing_over_quota_shared_workspace_can_still_invite_with_available_member_slots(): void
    {
        $owner = $this->userOnPlan('team');
        for ($number = 1; $number <= 3; $number++) {
            $this->createShared($owner);
        }
        $legacy = Workspace::create(['owner_id' => $owner->id, 'name' => 'Legacy', 'type' => 'shared']);
        $legacy->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);
        $invitee = User::factory()->create(['email' => 'legacy@example.com']);

        $this->invite($owner, $legacy, $invitee->email)->assertCreated();
        $invitation = WorkspaceInvitation::where('workspace_id', $legacy->id)->firstOrFail();
        $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->token}/accept")->assertOk();
        $this->actingAs($owner)->postJson('/api/workspaces', ['name' => 'Blocked'])->assertUnprocessable();

        $this->assertSame(4, app(PlanLimitService::class)->ownedSharedWorkspaceCount($owner));
        $this->assertSame(2, $legacy->members()->count());
    }

    public function test_member_count_does_not_change_shared_workspace_count(): void
    {
        $owner = $this->userOnPlan('team');
        $workspace = $this->createShared($owner);
        $member = User::factory()->create();

        $this->assertSame(1, app(PlanLimitService::class)->ownedSharedWorkspaceCount($owner));
        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $this->assertSame(1, app(PlanLimitService::class)->ownedSharedWorkspaceCount($owner));
        $workspace->members()->detach($member->id);
        $this->assertSame(1, app(PlanLimitService::class)->ownedSharedWorkspaceCount($owner));
    }

    public function test_shared_workspace_member_limit_still_applies_to_invitations_and_acceptance(): void
    {
        $owner = $this->userOnPlan('team');
        $owner->subscription->plan->update(['max_members_per_workspace' => 2]);
        $workspace = $this->createShared($owner);
        $invitee = User::factory()->create(['email' => 'first@example.com']);

        $this->invite($owner, $workspace, $invitee->email)->assertCreated();
        $this->invite($owner, $workspace, 'second@example.com')->assertUnprocessable();
        $invitation = WorkspaceInvitation::where('workspace_id', $workspace->id)->firstOrFail();
        $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->token}/accept")->assertOk();
        $this->assertSame(2, $workspace->members()->count());

        $this->invite($owner, $workspace, 'third@example.com')->assertUnprocessable();
        $this->assertSame(1, app(PlanLimitService::class)->ownedSharedWorkspaceCount($owner));
    }

    public function test_invitation_acceptance_rechecks_member_limit(): void
    {
        $owner = $this->userOnPlan('team');
        $owner->subscription->plan->update(['max_members_per_workspace' => 2]);
        $workspace = $this->createShared($owner);
        $invitee = User::factory()->create(['email' => 'late@example.com']);
        $this->invite($owner, $workspace, $invitee->email)->assertCreated();
        $workspace->members()->attach(User::factory()->create()->id, ['role' => 'member', 'joined_at' => now()]);
        $invitation = WorkspaceInvitation::where('workspace_id', $workspace->id)->firstOrFail();

        $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->token}/accept")->assertUnprocessable();

        $this->assertSame(2, $workspace->members()->count());
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_existing_shared_workspaces_survive_downgrade_but_new_ones_are_blocked(): void
    {
        $owner = $this->userOnPlan('team');
        $existing = $this->createShared($owner);
        $owner->subscription->update(['plan_id' => Plan::where('slug', 'free')->value('id')]);

        $this->actingAs($owner)->postJson('/api/workspaces', ['name' => 'New'])
            ->assertUnprocessable();

        $this->assertModelExists($existing);
        $this->assertSame(1, app(PlanLimitService::class)->ownedSharedWorkspaceCount($owner));
        $this->assertSame(1, $existing->members()->count());
    }

    public function test_downgrade_preserves_projects_and_members_but_blocks_new_over_limit_operations(): void
    {
        $owner = $this->userOnPlan('team');
        $personal = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $shared = $this->createShared($owner);
        $member = User::factory()->create();
        $shared->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        for ($number = 1; $number <= 12; $number++) {
            Board::create(['workspace_id' => $personal->id, 'name' => "Existing {$number}"]);
        }
        $owner->subscription->update(['plan_id' => Plan::where('slug', 'free')->value('id')]);

        $this->actingAs($owner)->getJson("/api/workspaces/{$personal->id}/boards")
            ->assertOk()->assertJsonCount(12, 'data');
        $this->createBoard($owner, $personal)->assertUnprocessable();
        $this->invite($owner, $shared, 'new@example.com')->assertUnprocessable();

        $this->assertSame(12, $personal->boards()->count());
        $this->assertTrue($shared->hasMember($member));
    }

    private function userOnPlan(string $slug): User
    {
        $user = User::factory()->create();
        $user->subscription()->update(['plan_id' => Plan::where('slug', $slug)->value('id')]);

        return $user->fresh();
    }

    private function createShared(User $owner): Workspace
    {
        $response = $this->actingAs($owner)->postJson('/api/workspaces', ['name' => 'Shared'])->assertCreated();

        return Workspace::findOrFail($response->json('data.id'));
    }

    private function createBoard(User $owner, Workspace $workspace): TestResponse
    {
        return $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->id}/boards", ['name' => 'Project '.Str::random(6)]);
    }

    private function invite(User $owner, Workspace $workspace, string $email): TestResponse
    {
        return $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->id}/invitations", ['email' => $email, 'role' => 'member']);
    }

    private function assertPlan(string $slug, int $price, ?int $projects, ?int $shared, ?int $members, bool $ai, int $credits): void
    {
        $plan = Plan::where('slug', $slug)->firstOrFail();
        $this->assertSame($price, $plan->price_cents);
        $this->assertSame($projects, $plan->max_projects);
        $this->assertSame($shared, $plan->max_shared_workspaces);
        $this->assertSame($members, $plan->max_members_per_workspace);
        $this->assertSame($ai, $plan->ai_enabled);
        $this->assertSame($credits, $plan->ai_monthly_credits);
    }
}
