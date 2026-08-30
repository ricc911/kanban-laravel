<?php

namespace Tests\Feature;

use App\Events\TaskCommentCreated;
use App\Events\TaskCommentDeleted;
use App\Events\TaskCommentUpdated;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TaskCommentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_workspace_roles_can_read_and_editors_can_create_comments(): void
    {
        [$owner, $workspace, $task] = $this->taskContext();
        $admin = User::factory()->create(['last_name' => 'Admin', 'username' => 'admin']);
        $member = User::factory()->create(['last_name' => 'Member', 'username' => 'member']);
        $viewer = User::factory()->create(['last_name' => 'Viewer', 'username' => 'viewer']);
        $outsider = User::factory()->create();
        $workspace->members()->attach([
            $admin->id => ['role' => 'admin', 'joined_at' => now()],
            $member->id => ['role' => 'member', 'joined_at' => now()],
            $viewer->id => ['role' => 'viewer', 'joined_at' => now()],
        ]);

        foreach ([$owner, $admin, $member, $viewer] as $actor) {
            $this->actingAs($actor)->getJson("/api/tasks/{$task->id}/comments")->assertOk();
        }
        $this->actingAs($outsider)->getJson("/api/tasks/{$task->id}/comments")->assertForbidden();

        Event::fake([TaskCommentCreated::class]);
        foreach ([$owner, $admin, $member] as $actor) {
            $this->actingAs($actor)->postJson("/api/tasks/{$task->id}/comments", ['body' => $actor->name])->assertCreated();
        }
        $this->actingAs($viewer)->postJson("/api/tasks/{$task->id}/comments", ['body' => 'no'])->assertForbidden();
        Event::assertDispatchedTimes(TaskCommentCreated::class, 3);
    }

    public function test_comment_body_is_trimmed_and_validated(): void
    {
        [$owner, , $task] = $this->taskContext();
        foreach (['', '   ', str_repeat('a', 5001)] as $body) {
            $this->actingAs($owner)->postJson("/api/tasks/{$task->id}/comments", ['body' => $body])->assertUnprocessable();
        }
    }

    public function test_comment_uses_authenticated_author_and_safe_payload(): void
    {
        [$owner, , $task] = $this->taskContext();
        Event::fake([TaskCommentCreated::class]);
        $response = $this->actingAs($owner)->postJson("/api/tasks/{$task->id}/comments", [
            'body' => "  commento\nseconda riga  ",
            'user_id' => 999999,
        ])->assertCreated();
        $response->assertJsonPath('data.author.id', $owner->id)
            ->assertJsonPath('data.body', "commento\nseconda riga")
            ->assertJsonMissingPath('data.author.email');
        Event::assertDispatched(TaskCommentCreated::class, function (TaskCommentCreated $event): bool {
            return $event->broadcastAs() === 'task.comment_created'
                && $event->broadcastOn()[0]->name === 'private-board.'.$event->boardId
                && $event->broadcastWith()['comments_count'] === 1
                && ! array_key_exists('email', $event->broadcastWith()['comment']['author']);
        });
    }

    public function test_only_author_can_update_and_owner_or_admin_can_moderate_delete(): void
    {
        [$owner, $workspace, $task] = $this->taskContext();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $workspace->members()->attach([
            $admin->id => ['role' => 'admin', 'joined_at' => now()],
            $member->id => ['role' => 'member', 'joined_at' => now()],
        ]);
        $comment = $task->comments()->create(['user_id' => $member->id, 'body' => 'original']);
        Event::fake([TaskCommentUpdated::class, TaskCommentDeleted::class]);
        $this->actingAs($owner)->patchJson("/api/task-comments/{$comment->id}", ['body' => 'owner no'])->assertForbidden();
        $this->actingAs($member)->patchJson("/api/task-comments/{$comment->id}", ['body' => 'updated'])->assertOk();
        $this->actingAs($admin)->deleteJson("/api/task-comments/{$comment->id}")->assertNoContent();
        Event::assertDispatched(TaskCommentUpdated::class);
        Event::assertDispatched(TaskCommentDeleted::class);
    }

    public function test_identical_update_does_not_dispatch_update_event_and_delete_keeps_count_consistent(): void
    {
        [$owner, , $task] = $this->taskContext();
        $comment = $task->comments()->create(['user_id' => $owner->id, 'body' => 'same']);
        Event::fake([TaskCommentUpdated::class]);
        $this->actingAs($owner)->patchJson("/api/task-comments/{$comment->id}", ['body' => ' same '])->assertOk();
        Event::assertNotDispatched(TaskCommentUpdated::class);
        $this->assertSame(1, $task->comments()->count());
    }

    public function test_comment_and_task_deletion_cascades_and_deleted_user_is_safe(): void
    {
        [$owner, $workspace, $task] = $this->taskContext();
        $author = User::factory()->create();
        $workspace->members()->attach($author->id, ['role' => 'member', 'joined_at' => now()]);
        $comment = $task->comments()->create(['user_id' => $author->id, 'body' => 'history']);
        $author->delete();
        $this->assertDatabaseHas('task_comments', ['id' => $comment->id, 'user_id' => null]);
        $task->delete();
        $this->assertDatabaseMissing('task_comments', ['id' => $comment->id]);
    }

    /** @return array{0: User, 1: Workspace, 2: Task} */
    private function taskContext(): array
    {
        $owner = User::factory()->create(['name' => 'Mario', 'last_name' => 'Rossi', 'username' => 'mario']);
        $workspace = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Board']);
        $column = BoardColumn::create(['board_id' => $board->id, 'name' => 'Da fare', 'position' => 1000]);
        $task = Task::create(['board_id' => $board->id, 'board_column_id' => $column->id, 'title' => 'Task', 'position' => 1000]);

        return [$owner, $workspace, $task];
    }
}
