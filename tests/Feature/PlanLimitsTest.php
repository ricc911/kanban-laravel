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

    public function test_free_allows_one_project_then_blocks_the_second_and_membership(): void
    {
        $owner = $this->userOnPlan('free');
        $workspace = $owner->ownedWorkspaces()->firstOrFail();

        $this->createBoard($owner, $workspace)->assertCreated();
        $this->createBoard($owner, $workspace)->assertUnprocessable();
        $this->actingAs($owner)->postJson('/api/workspaces', ['name' => 'Free owner only'])->assertCreated();
        $shared = Workspace::where('owner_id', $owner->id)->where('type', 'shared')->latest('id')->firstOrFail();
        $this->invite($owner, $shared, 'free-member@example.com')->assertUnprocessable();
        $this->assertSame(0, (int) $owner->subscription->fresh()->plan->ai_monthly_credits);
    }

    public function test_pro_allows_ten_projects_but_blocks_the_eleventh_and_second_member(): void
    {
        $owner = $this->userOnPlan('pro');
        $workspace = $owner->ownedWorkspaces()->firstOrFail();

        for ($number = 1; $number <= 10; $number++) {
            $this->createBoard($owner, $workspace)->assertCreated();
        }

        $this->createBoard($owner, $workspace)->assertUnprocessable();
        $this->actingAs($owner)->postJson('/api/workspaces', ['name' => 'Pro owner only'])->assertCreated();
        $shared = Workspace::where('owner_id', $owner->id)->where('type', 'shared')->latest('id')->firstOrFail();
        $this->invite($owner, $shared, 'pro-member@example.com')->assertUnprocessable();
        $this->assertSame(500, (int) $owner->subscription->fresh()->plan->ai_monthly_credits);
    }

    public function test_team_enforces_fifty_projects_three_shared_workspaces_and_ten_total_members(): void
    {
        $owner = $this->userOnPlan('team');
        $workspace = $owner->ownedWorkspaces()->firstOrFail();

        for ($number = 1; $number <= 50; $number++) {
            Board::create(['workspace_id' => $workspace->id, 'name' => "Project {$number}"]);
        }

        $this->createBoard($owner, $workspace)->assertUnprocessable();
        $shared = $this->makeEffectivelyShared($owner, 'team-shared-1@example.com');
        $this->makeEffectivelyShared($owner, 'team-shared-2@example.com');
        $this->makeEffectivelyShared($owner, 'team-shared-3@example.com');
        $this->assertSame(3, app(PlanLimitService::class)->sharedWorkspaceCount($owner));
        $this->actingAs($owner)->postJson('/api/workspaces', ['name' => 'Team 4'])->assertCreated();
        $ownerOnly = Workspace::where('owner_id', $owner->id)->where('type', 'shared')->latest('id')->firstOrFail();
        $this->invite($owner, $ownerOnly, 'team-fourth@example.com')->assertUnprocessable();
        $this->assertSame(3, app(PlanLimitService::class)->sharedWorkspaceCount($owner));
        $members = User::factory()->count(7)->create();
        foreach ($members as $member) {
            $shared->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        }

        $tenthMember = User::factory()->create(['email' => 'team-tenth@example.com']);
        $this->invite($owner, $shared, $tenthMember->email)->assertCreated();
        $invitation = WorkspaceInvitation::where('email', $tenthMember->email)->firstOrFail();
        $this->actingAs($tenthMember)->postJson("/api/invitations/{$invitation->token}/accept")->assertOk();
        $this->assertSame(10, $shared->fresh()->members()->count());

        $eleventh = User::factory()->create(['email' => 'team-eleventh@example.com']);
        $this->invite($owner, $shared, $eleventh->email)->assertUnprocessable();
        $this->assertSame(3000, (int) $owner->subscription->fresh()->plan->ai_monthly_credits);
    }

    public function test_team_personal_workspace_does_not_consume_shared_quota_but_cannot_accept_members(): void
    {
        $owner = $this->userOnPlan('team');
        $personal = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();

        $this->makeEffectivelyShared($owner, 'shared-one@example.com');
        $this->makeEffectivelyShared($owner, 'shared-two@example.com');
        $this->makeEffectivelyShared($owner, 'shared-three@example.com');
        $this->assertSame(3, app(PlanLimitService::class)->sharedWorkspaceCount($owner));
        $ownerOnly = $this->createShared($owner);
        $this->invite($owner, $ownerOnly, 'personal-member@example.com')->assertUnprocessable();
        $this->assertSame(3, app(PlanLimitService::class)->sharedWorkspaceCount($owner));
        $this->assertSame(1, $personal->fresh()->members()->count());
    }

    public function test_acceptance_rechecks_member_limit_after_invitation_was_created(): void
    {
        $owner = $this->userOnPlan('team');
        $workspace = $this->createShared($owner);
        $existing = User::factory()->count(8)->create();
        foreach ($existing as $member) {
            $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        }
        $invitee = User::factory()->create(['email' => 'late-invite@example.com']);
        $this->invite($owner, $workspace, $invitee->email)->assertCreated();
        $workspace->members()->attach(User::factory()->create()->id, ['role' => 'member', 'joined_at' => now()]);
        $invitation = WorkspaceInvitation::where('email', $invitee->email)->firstOrFail();

        $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->token}/accept")->assertUnprocessable();
        $this->assertSame(10, $workspace->fresh()->members()->count());
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_removing_the_last_non_owner_member_frees_a_shared_workspace_slot(): void
    {
        $owner = $this->userOnPlan('team');
        $this->makeEffectivelyShared($owner, 'remove-first@example.com');
        $this->makeEffectivelyShared($owner, 'remove-second@example.com');
        $third = $this->makeEffectivelyShared($owner, 'remove-third@example.com');
        $ownerOnly = $this->createShared($owner);
        $member = $third->members()->where('users.id', '!=', $owner->id)->firstOrFail();

        $this->actingAs($owner)->deleteJson("/api/workspaces/{$third->id}/members/{$member->id}")->assertNoContent();
        $this->assertSame(2, app(PlanLimitService::class)->sharedWorkspaceCount($owner));
        $this->assertSame('shared', $third->fresh()->type);

        $newMember = User::factory()->create(['email' => 'remove-fourth@example.com']);
        $this->invite($owner, $ownerOnly, $newMember->email)->assertCreated();
        $invitation = WorkspaceInvitation::where('email', $newMember->email)->firstOrFail();
        $this->actingAs($newMember)->postJson("/api/invitations/{$invitation->token}/accept")->assertOk();
        $this->assertSame(3, app(PlanLimitService::class)->sharedWorkspaceCount($owner));
        $this->assertModelExists($third);
    }

    public function test_member_leave_frees_a_shared_workspace_slot(): void
    {
        $owner = $this->userOnPlan('team');
        $this->makeEffectivelyShared($owner, 'leave-first@example.com');
        $this->makeEffectivelyShared($owner, 'leave-second@example.com');
        $third = $this->makeEffectivelyShared($owner, 'leave-third@example.com');
        $member = $third->members()->where('users.id', '!=', $owner->id)->firstOrFail();

        $this->actingAs($member)->deleteJson("/api/workspaces/{$third->id}/leave")->assertNoContent();
        $this->assertSame(2, app(PlanLimitService::class)->sharedWorkspaceCount($owner));
        $this->assertSame(1, $third->fresh()->members()->count());
    }

    public function test_pending_invitation_reserves_one_shared_slot_per_owner_only_workspace(): void
    {
        $owner = $this->userOnPlan('team');
        $this->makeEffectivelyShared($owner, 'pending-first@example.com');
        $this->makeEffectivelyShared($owner, 'pending-second@example.com');
        $pendingWorkspace = $this->createShared($owner);
        $otherWorkspace = $this->createShared($owner);

        $this->invite($owner, $pendingWorkspace, 'pending-one@example.com')->assertCreated();
        $this->invite($owner, $pendingWorkspace, 'pending-two@example.com')->assertCreated();

        $limits = app(PlanLimitService::class);
        $this->assertSame(2, $limits->sharedWorkspaceCount($owner));
        $this->assertSame(1, $limits->reservedSharedWorkspaceCount($owner));
        $this->invite($owner, $otherWorkspace, 'pending-other@example.com')->assertUnprocessable();
    }

    public function test_expired_invitation_releases_owner_only_shared_reservation(): void
    {
        $owner = $this->userOnPlan('team');
        $this->makeEffectivelyShared($owner, 'expiry-first@example.com');
        $this->makeEffectivelyShared($owner, 'expiry-second@example.com');
        $pendingWorkspace = $this->createShared($owner);
        $otherWorkspace = $this->createShared($owner);

        $this->invite($owner, $pendingWorkspace, 'expired@example.com')->assertCreated();
        WorkspaceInvitation::where('email', 'expired@example.com')->update(['expires_at' => now()->subMinute()]);

        $limits = app(PlanLimitService::class);
        $this->assertSame(0, $limits->reservedSharedWorkspaceCount($owner));
        $this->invite($owner, $otherWorkspace, 'available@example.com')->assertCreated();
    }

    public function test_acceptance_rechecks_shared_workspace_limit_after_other_workspace_becomes_shared(): void
    {
        $owner = $this->userOnPlan('team');
        $this->makeEffectivelyShared($owner, 'accept-first@example.com');
        $this->makeEffectivelyShared($owner, 'accept-second@example.com');
        $pendingWorkspace = $this->createShared($owner);
        $newMember = User::factory()->create(['email' => 'accept-pending@example.com']);
        $this->invite($owner, $pendingWorkspace, $newMember->email)->assertCreated();

        $otherOwnerOnly = $this->createShared($owner);
        $otherMember = User::factory()->create(['email' => 'accept-other@example.com']);
        $otherOwnerOnly->members()->attach($otherMember->id, ['role' => 'member', 'joined_at' => now()]);
        $invitation = WorkspaceInvitation::where('email', $newMember->email)->firstOrFail();

        $this->actingAs($newMember)->postJson("/api/invitations/{$invitation->token}/accept")->assertUnprocessable();
        $this->assertSame(1, $pendingWorkspace->fresh()->members()->count());
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_business_has_unlimited_projects_and_enforces_ten_shared_workspaces_and_forty_members(): void
    {
        $owner = $this->userOnPlan('business');
        $workspace = $owner->ownedWorkspaces()->firstOrFail();

        for ($number = 1; $number <= 3; $number++) {
            Board::create(['workspace_id' => $workspace->id, 'name' => "Project {$number}"]);
        }
        $this->createBoard($owner, $workspace)->assertCreated();

        for ($number = 1; $number <= 9; $number++) {
            $this->makeEffectivelyShared($owner, "business-{$number}@example.com");
        }
        $shared = $this->makeEffectivelyShared($owner, 'business-tenth@example.com');
        $this->assertSame(10, app(PlanLimitService::class)->sharedWorkspaceCount($owner));
        $ownerOnly = $this->createShared($owner);
        $this->assertNotNull($ownerOnly);
        $members = User::factory()->count(38)->create();
        foreach ($members as $member) {
            $shared->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        }
        $this->assertSame(40, $shared->fresh()->members()->count());
        $this->invite($owner, $shared, 'business-extra@example.com')->assertUnprocessable();
        $this->assertSame(10000, (int) $owner->subscription->fresh()->plan->ai_monthly_credits);
    }

    public function test_downgrade_preserves_existing_data_and_blocks_only_new_over_limit_operations(): void
    {
        $owner = $this->userOnPlan('business');
        $workspace = $owner->ownedWorkspaces()->firstOrFail();
        for ($number = 1; $number <= 12; $number++) {
            Board::create(['workspace_id' => $workspace->id, 'name' => "Existing {$number}"]);
        }
        $owner->subscription->update(['plan_id' => Plan::where('slug', 'pro')->value('id')]);

        $this->assertSame(12, $workspace->fresh()->boards()->count());
        $this->actingAs($owner)->getJson("/api/workspaces/{$workspace->id}/boards")->assertOk()->assertJsonCount(12, 'data');
        $this->createBoard($owner, $workspace)->assertUnprocessable();
    }

    public function test_downgrade_preserves_members_but_blocks_new_invitation(): void
    {
        $owner = $this->userOnPlan('team');
        $workspace = $this->createShared($owner);
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $owner->subscription->update(['plan_id' => Plan::where('slug', 'free')->value('id')]);

        $this->assertSame(2, $workspace->fresh()->members()->count());
        $this->invite($owner, $workspace, 'new-after-downgrade@example.com')->assertUnprocessable();
        $this->assertTrue($workspace->fresh()->hasMember($member));
    }

    private function userOnPlan(string $slug): User
    {
        $user = User::factory()->create();
        $user->subscription()->update(['plan_id' => Plan::where('slug', $slug)->value('id')]);

        return $user->fresh();
    }

    private function createShared(User $owner): Workspace
    {
        $this->actingAs($owner)->postJson('/api/workspaces', ['name' => 'Shared'])->assertCreated();

        return Workspace::where('owner_id', $owner->id)->where('type', 'shared')->latest('id')->firstOrFail();
    }

    private function makeEffectivelyShared(User $owner, string $email): Workspace
    {
        $workspace = $this->createShared($owner);
        $invitee = User::factory()->create(['email' => $email]);
        $this->invite($owner, $workspace, $invitee->email)->assertCreated();
        $invitation = WorkspaceInvitation::where('workspace_id', $workspace->id)->where('email', $invitee->email)->firstOrFail();
        $this->actingAs($invitee)->postJson("/api/invitations/{$invitation->token}/accept")->assertOk();

        return $workspace->fresh();
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
