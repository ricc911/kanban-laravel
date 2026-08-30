<?php

namespace Tests\Feature;

use App\Events\ActivityLogged;
use App\Events\TaskAssigneesChanged;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TaskAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_owner_can_assign_a_workspace_member_and_viewer_is_assignable_but_remains_read_only(): void
    {
        [$owner, $workspace, $board, $column] = $this->board();
        $viewer = User::factory()->create();
        $workspace->members()->attach($viewer->id, ['role' => 'viewer', 'joined_at' => now()]);
        $task = $this->task($board, $column);
        Event::fake([TaskAssigneesChanged::class, ActivityLogged::class]);

        $this->actingAs($owner)->postJson("/api/tasks/{$task->id}/assignees", ['user_id' => $viewer->id])->assertOk();

        $this->assertDatabaseHas('task_assignees', ['task_id' => $task->id, 'user_id' => $viewer->id]);
        $this->actingAs($viewer)->patchJson("/api/tasks/{$task->id}", [
            'title' => 'Modificata', 'description' => null, 'priority' => null, 'due_at' => null, 'category_id' => null,
        ])->assertUnprocessable();
    }

    public function test_member_and_admin_can_assign_but_viewer_and_outsider_cannot(): void
    {
        [$owner, $workspace, $board, $column] = $this->board();
        $member = User::factory()->create();
        $admin = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace->members()->attach([
            $member->id => ['role' => 'member', 'joined_at' => now()],
            $admin->id => ['role' => 'admin', 'joined_at' => now()],
            $viewer->id => ['role' => 'viewer', 'joined_at' => now()],
        ]);
        $task = $this->task($board, $column);
        Event::fake([TaskAssigneesChanged::class, ActivityLogged::class]);

        foreach ([$member, $admin] as $actor) {
            $this->actingAs($actor)->postJson("/api/tasks/{$task->id}/assignees", ['user_id' => $owner->id])->assertOk();
        }
        foreach ([$viewer, $outsider] as $actor) {
            $this->actingAs($actor)->postJson("/api/tasks/{$task->id}/assignees", ['user_id' => $owner->id])->assertForbidden();
        }
    }

    public function test_only_content_editors_can_remove_assignees(): void
    {
        [$owner, $workspace, $board, $column] = $this->board();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace->members()->attach([
            $admin->id => ['role' => 'admin', 'joined_at' => now()],
            $member->id => ['role' => 'member', 'joined_at' => now()],
            $viewer->id => ['role' => 'viewer', 'joined_at' => now()],
        ]);
        $task = $this->task($board, $column);
        $task->assignees()->attach($owner->id);
        Event::fake([TaskAssigneesChanged::class, ActivityLogged::class]);

        foreach ([$admin, $member] as $actor) {
            $task->assignees()->syncWithoutDetaching($owner->id);
            $this->actingAs($actor)->deleteJson("/api/tasks/{$task->id}/assignees/{$owner->id}")->assertOk();
        }
        foreach ([$viewer, $outsider] as $actor) {
            $this->actingAs($actor)->deleteJson("/api/tasks/{$task->id}/assignees/{$owner->id}")->assertForbidden();
        }
    }

    public function test_assign_and_unassign_are_idempotent_and_broadcast_the_final_list(): void
    {
        [$owner, $workspace, $board, $column] = $this->board();
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $task = $this->task($board, $column);
        Event::fake([TaskAssigneesChanged::class, ActivityLogged::class]);

        $this->actingAs($owner)->postJson("/api/tasks/{$task->id}/assignees", ['user_id' => $member->id])->assertOk();
        $this->actingAs($owner)->postJson("/api/tasks/{$task->id}/assignees", ['user_id' => $member->id])->assertOk();
        Event::assertDispatchedTimes(TaskAssigneesChanged::class, 1);
        Event::assertDispatchedTimes(ActivityLogged::class, 1);

        $this->actingAs($owner)->deleteJson("/api/tasks/{$task->id}/assignees/{$member->id}")->assertOk();
        $this->actingAs($owner)->deleteJson("/api/tasks/{$task->id}/assignees/{$member->id}")->assertOk();
        Event::assertDispatchedTimes(TaskAssigneesChanged::class, 2);
        Event::assertDispatchedTimes(ActivityLogged::class, 2);
        $this->assertDatabaseMissing('task_assignees', ['task_id' => $task->id, 'user_id' => $member->id]);
    }

    public function test_board_show_contains_safe_assignees_and_workspace_members(): void
    {
        [$owner, $workspace, $board, $column] = $this->board();
        $member = User::factory()->create(['last_name' => 'Rossi', 'username' => 'mario']);
        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $task = $this->task($board, $column);
        $task->assignees()->attach($member->id);

        $response = $this->actingAs($owner)->getJson("/api/boards/{$board->id}")->assertOk();
        $assignee = collect($response->json('data.columns.0.tasks.0.assignees'))->firstWhere('id', $member->id);
        $workspaceMember = collect($response->json('data.workspace_members'))->firstWhere('id', $member->id);

        $this->assertSame(['id', 'name', 'last_name', 'username'], array_keys($assignee));
        $this->assertArrayNotHasKey('email', $assignee);
        $this->assertSame('member', $workspaceMember['role']);
        $this->assertArrayNotHasKey('password', $workspaceMember);
        $this->assertArrayNotHasKey('members', $response->json('data.workspace'));
    }

    public function test_assignments_are_removed_when_member_is_removed_or_leaves(): void
    {
        [$owner, $workspace, $board, $column] = $this->board();
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $task = $this->task($board, $column);
        $task->assignees()->attach($member->id);
        Event::fake([TaskAssigneesChanged::class, ActivityLogged::class]);

        $this->actingAs($owner)->deleteJson("/api/workspaces/{$workspace->id}/members/{$member->id}")->assertNoContent();
        $this->assertDatabaseMissing('task_assignees', ['task_id' => $task->id, 'user_id' => $member->id]);

        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $task->assignees()->attach($member->id);
        $this->actingAs($member)->deleteJson("/api/workspaces/{$workspace->id}/leave")->assertNoContent();
        $this->assertDatabaseMissing('task_assignees', ['task_id' => $task->id, 'user_id' => $member->id]);
    }

    public function test_personal_owner_can_assign_themselves_and_foreign_workspace_members_are_rejected(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Personale', 'type' => 'personal']);
        $workspace->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Board']);
        $column = BoardColumn::create(['board_id' => $board->id, 'name' => 'To do', 'position' => 1000]);
        $other = User::factory()->create();
        $task = $this->task($board, $column);
        Event::fake([TaskAssigneesChanged::class, ActivityLogged::class]);

        $this->actingAs($owner)->postJson("/api/tasks/{$task->id}/assignees", ['user_id' => $owner->id])->assertOk();
        $this->actingAs($owner)->postJson("/api/tasks/{$task->id}/assignees", ['user_id' => $other->id])->assertUnprocessable();
    }

    public function test_task_and_user_deletion_cascade_the_assignment_pivot(): void
    {
        [$owner, $workspace, $board, $column] = $this->board();
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $task = $this->task($board, $column);
        $task->assignees()->attach($member->id);

        $task->delete();
        $this->assertDatabaseMissing('task_assignees', ['user_id' => $member->id]);

        $secondTask = $this->task($board, $column);
        $secondTask->assignees()->attach($member->id);
        $member->delete();
        $this->assertDatabaseMissing('task_assignees', ['task_id' => $secondTask->id]);
    }

    /** @return array{0: User, 1: Workspace, 2: Board, 3: BoardColumn} */
    private function board(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Team', 'type' => 'shared']);
        $workspace->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Board']);
        $column = BoardColumn::create(['board_id' => $board->id, 'name' => 'To do', 'position' => 1000]);

        return [$owner, $workspace, $board, $column];
    }

    private function task(Board $board, BoardColumn $column): Task
    {
        return Task::create(['board_id' => $board->id, 'board_column_id' => $column->id, 'title' => 'Task', 'position' => 1000]);
    }
}
