<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_free_can_create_owner_only_workspace_but_cannot_add_a_member(): void
    {
        $free = User::factory()->create();
        $response = $this->actingAs($free)->postJson('/api/workspaces', ['name' => 'No'])->assertCreated();
        $workspace = Workspace::findOrFail($response->json('data.id'));
        $this->actingAs($free)->postJson("/api/workspaces/{$workspace->id}/invitations", ['email' => 'free-member@example.com'])->assertUnprocessable();

        $team = $this->teamUser();
        $this->actingAs($team)->postJson('/api/workspaces', ['name' => 'Team'])->assertCreated();
        $this->assertDatabaseHas('workspaces', ['name' => 'Team', 'type' => 'shared']);
    }

    public function test_shared_workspace_limit_is_enforced(): void
    {
        $owner = $this->teamUser();
        for ($i = 0; $i < 3; $i++) {
            $workspace = $this->createShared($owner);
            $workspace->members()->attach(User::factory()->create()->id, ['role' => 'member', 'joined_at' => now()]);
        }
        $this->actingAs($owner)->postJson('/api/workspaces', ['name' => 'Owner only'])->assertCreated();
    }

    public function test_owner_can_invite_but_member_cannot_and_member_limit_is_enforced(): void
    {
        $owner = $this->teamUser();
        $workspace = $this->createShared($owner);
        $member = User::factory()->create(['email' => 'member@example.com']);
        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->id}/invitations", ['email' => 'guest@example.com'])->assertCreated();
        $this->actingAs($member)->postJson("/api/workspaces/{$workspace->id}/invitations", ['email' => 'other@example.com'])->assertUnprocessable();

        $workspace->owner->subscription->plan->update(['max_members_per_workspace' => 2]);
        $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->id}/invitations", ['email' => 'full@example.com'])->assertUnprocessable();
    }

    public function test_invitation_acceptance_makes_workspace_visible(): void
    {
        $owner = $this->teamUser();
        $workspace = $this->createShared($owner);
        $member = User::factory()->create(['email' => 'accepted@example.com']);
        $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->id}/invitations", ['email' => $member->email])->assertCreated();
        $invitation = WorkspaceInvitation::query()->where('email', $member->email)->firstOrFail();

        $this->actingAs($member)->postJson("/api/invitations/{$invitation->token}/accept")->assertOk();
        $this->assertTrue($member->fresh()->isMemberOf($workspace));
        $this->assertTrue(collect($this->actingAs($member)->getJson('/api/workspaces')->json('data'))->contains('id', $workspace->id));
    }

    public function test_owner_can_remove_member_but_member_cannot_remove_others(): void
    {
        $owner = $this->teamUser();
        $workspace = $this->createShared($owner);
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($member)->deleteJson("/api/workspaces/{$workspace->id}/members/{$owner->id}")->assertUnprocessable();
        $this->actingAs($owner)->deleteJson("/api/workspaces/{$workspace->id}/members/{$member->id}")->assertNoContent();
        $this->assertFalse($workspace->fresh()->hasMember($member));
    }

    public function test_member_can_leave_but_owner_cannot(): void
    {
        $owner = $this->teamUser();
        $workspace = $this->createShared($owner);
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($owner)->deleteJson("/api/workspaces/{$workspace->id}/leave")->assertUnprocessable();
        $this->actingAs($member)->deleteJson("/api/workspaces/{$workspace->id}/leave")->assertNoContent();
    }

    public function test_external_user_cannot_view_members_or_invitations_and_personal_workspace_cannot_use_shared_operations(): void
    {
        $owner = $this->teamUser();
        $workspace = $this->createShared($owner);
        $external = User::factory()->create();
        $this->actingAs($external)->getJson("/api/workspaces/{$workspace->id}/members")->assertForbidden();
        $this->actingAs($external)->getJson("/api/workspaces/{$workspace->id}/invitations")->assertForbidden();

        $personal = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $this->actingAs($owner)->postJson("/api/workspaces/{$personal->id}/invitations", ['email' => $external->email])->assertUnprocessable();
        $this->actingAs($owner)->deleteJson("/api/workspaces/{$personal->id}/leave")->assertUnprocessable();
    }

    private function teamUser(): User
    {
        $user = User::factory()->create();
        $user->subscription()->update(['plan_id' => Plan::where('slug', 'team')->value('id')]);

        return $user;
    }

    private function createShared(User $owner): Workspace
    {
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Shared', 'type' => 'shared']);
        $workspace->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);

        return $workspace;
    }
}
