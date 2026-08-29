<?php

namespace Tests\Feature;

use App\Events\ActivityLogged;
use App\Events\UserRealtimeEvent;
use App\Events\WorkspaceChanged;
use App\Models\ActivityLog;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_owner_can_invite_each_supported_role_but_not_owner(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();

        foreach (['admin', 'member', 'viewer'] as $role) {
            $response = $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->id}/invitations", [
                'email' => "{$role}@example.com",
                'role' => $role,
            ]);
            $response->assertCreated();
            $this->assertDatabaseHas('workspace_invitations', ['email' => "{$role}@example.com", 'role' => $role]);
        }

        $this->actingAs($owner)->postJson("/api/workspaces/{$workspace->id}/invitations", [
            'email' => 'owner@example.com',
            'role' => 'owner',
        ])->assertUnprocessable();
    }

    public function test_admin_can_invite_members_and_viewers_but_not_admins(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();
        $admin = $this->addMember($workspace, 'admin');

        foreach (['member', 'viewer'] as $role) {
            $this->actingAs($admin)->postJson("/api/workspaces/{$workspace->id}/invitations", [
                'email' => "{$role}@example.com",
                'role' => $role,
            ])->assertCreated();
        }

        $this->actingAs($admin)->postJson("/api/workspaces/{$workspace->id}/invitations", [
            'email' => 'another-admin@example.com',
            'role' => 'admin',
        ])->assertUnprocessable();
    }

    public function test_member_and_viewer_cannot_invite(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();
        $member = $this->addMember($workspace, 'member');
        $viewer = $this->addMember($workspace, 'viewer');

        foreach ([$member, $viewer] as $user) {
            $this->actingAs($user)->postJson("/api/workspaces/{$workspace->id}/invitations", [
                'email' => fake()->safeEmail(),
                'role' => 'member',
            ])->assertUnprocessable();
        }
    }

    public function test_acceptance_applies_admin_and_viewer_roles(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();

        foreach (['admin', 'viewer'] as $role) {
            $user = User::factory()->create(['email' => "{$role}-accept@example.com"]);
            $invitation = WorkspaceInvitation::create([
                'workspace_id' => $workspace->id,
                'email' => $user->email,
                'role' => $role,
                'token' => Str::random(64),
                'expires_at' => now()->addDay(),
            ]);

            $this->actingAs($user)->postJson("/api/invitations/{$invitation->token}/accept")->assertOk();
            $this->assertDatabaseHas('workspace_members', ['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => $role]);
        }
    }

    public function test_owner_can_change_roles_but_cannot_change_self_or_assign_owner(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();
        $member = $this->addMember($workspace, 'member');

        foreach (['admin', 'member', 'viewer'] as $role) {
            $this->actingAs($owner)->patchJson("/api/workspaces/{$workspace->id}/members/{$member->id}/role", ['role' => $role])->assertNoContent();
        }

        $this->actingAs($owner)->patchJson("/api/workspaces/{$workspace->id}/members/{$owner->id}/role", ['role' => 'member'])->assertUnprocessable();
        $this->actingAs($owner)->patchJson("/api/workspaces/{$workspace->id}/members/{$member->id}/role", ['role' => 'owner'])->assertUnprocessable();
    }

    public function test_admin_can_change_member_viewer_but_cannot_manage_admin_or_assign_admin(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();
        $admin = $this->addMember($workspace, 'admin');
        $member = $this->addMember($workspace, 'member');
        $otherAdmin = $this->addMember($workspace, 'admin');

        $this->actingAs($admin)->patchJson("/api/workspaces/{$workspace->id}/members/{$member->id}/role", ['role' => 'viewer'])->assertNoContent();
        $this->actingAs($admin)->patchJson("/api/workspaces/{$workspace->id}/members/{$member->id}/role", ['role' => 'admin'])->assertUnprocessable();
        $this->actingAs($admin)->patchJson("/api/workspaces/{$workspace->id}/members/{$otherAdmin->id}/role", ['role' => 'member'])->assertUnprocessable();
        $this->actingAs($admin)->patchJson("/api/workspaces/{$workspace->id}/members/{$owner->id}/role", ['role' => 'member'])->assertUnprocessable();
    }

    public function test_only_owner_and_admin_can_remove_allowed_members(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();
        $admin = $this->addMember($workspace, 'admin');
        $member = $this->addMember($workspace, 'member');
        $viewer = $this->addMember($workspace, 'viewer');
        $otherAdmin = $this->addMember($workspace, 'admin');

        $this->actingAs($admin)->deleteJson("/api/workspaces/{$workspace->id}/members/{$member->id}")->assertNoContent();
        $this->actingAs($admin)->deleteJson("/api/workspaces/{$workspace->id}/members/{$viewer->id}")->assertNoContent();
        $this->actingAs($admin)->deleteJson("/api/workspaces/{$workspace->id}/members/{$otherAdmin->id}")->assertUnprocessable();
        $this->actingAs($admin)->deleteJson("/api/workspaces/{$workspace->id}/members/{$owner->id}")->assertUnprocessable();
    }

    public function test_viewer_can_read_but_cannot_mutate_content(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();
        $viewer = $this->addMember($workspace, 'viewer');
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Board']);
        $column = BoardColumn::create(['board_id' => $board->id, 'name' => 'Todo', 'position' => 1000]);
        $task = Task::create(['board_id' => $board->id, 'board_column_id' => $column->id, 'title' => 'Task', 'position' => 1000]);

        $this->actingAs($viewer)->getJson('/api/workspaces')->assertOk();
        $this->actingAs($viewer)->getJson("/api/boards/{$board->id}")->assertOk();
        $this->actingAs($viewer)->postJson("/api/workspaces/{$workspace->id}/folders", ['name' => 'No'])->assertUnprocessable();
        $this->actingAs($viewer)->patchJson("/api/tasks/{$task->id}", ['title' => 'No'])->assertUnprocessable();
        $this->actingAs($viewer)->deleteJson("/api/tasks/{$task->id}")->assertUnprocessable();
    }

    public function test_member_can_create_content_and_personal_workspace_rejects_membership_operations(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();
        $member = $this->addMember($workspace, 'member');

        $this->actingAs($member)->postJson("/api/workspaces/{$workspace->id}/folders", ['name' => 'Folder'])->assertCreated();
        $personal = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $this->actingAs($owner)->postJson("/api/workspaces/{$personal->id}/invitations", ['email' => 'x@example.com'])->assertUnprocessable();
        $this->actingAs($owner)->patchJson("/api/workspaces/{$personal->id}/members/{$owner->id}/role", ['role' => 'viewer'])->assertUnprocessable();
    }

    public function test_role_update_dispatches_realtime_events_and_activity_metadata(): void
    {
        Event::fake([WorkspaceChanged::class, UserRealtimeEvent::class, ActivityLogged::class]);
        [$owner, $workspace] = $this->sharedWorkspace();
        $member = $this->addMember($workspace, 'member');

        $this->actingAs($owner)->patchJson("/api/workspaces/{$workspace->id}/members/{$member->id}/role", ['role' => 'viewer'])->assertNoContent();

        Event::assertDispatched(WorkspaceChanged::class, fn (WorkspaceChanged $event): bool => $event->action === 'workspace.member_role_updated');
        Event::assertDispatched(UserRealtimeEvent::class, fn (UserRealtimeEvent $event): bool => $event->action === 'workspace.role_updated' && $event->userId === $member->id && $event->payload['role'] === 'viewer');
        $this->assertDatabaseHas('activity_logs', ['action' => 'workspace.member_role_updated', 'workspace_id' => $workspace->id]);
        $this->assertEquals('member', $this->latestActivity($workspace)->metadata['old_role']);
        $this->assertEquals('viewer', $this->latestActivity($workspace)->metadata['new_role']);
    }

    private function teamUser(): User
    {
        $user = User::factory()->create();
        $user->subscription()->update(['plan_id' => Plan::where('slug', 'team')->value('id')]);

        return $user;
    }

    /** @return array{0: User, 1: Workspace} */
    private function sharedWorkspace(): array
    {
        $owner = $this->teamUser();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Shared', 'type' => 'shared']);
        $workspace->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);

        return [$owner, $workspace];
    }

    private function addMember(Workspace $workspace, string $role): User
    {
        $user = User::factory()->create();
        $workspace->members()->attach($user->id, ['role' => $role, 'joined_at' => now()]);

        return $user;
    }

    private function latestActivity(Workspace $workspace): ActivityLog
    {
        return $workspace->activityLogs()->latest('id')->firstOrFail();
    }
}
