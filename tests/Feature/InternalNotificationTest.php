<?php

namespace Tests\Feature;

use App\Events\UserRealtimeEvent;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InternalNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_assignment_creates_safe_notification_and_realtime_event_for_assignee(): void
    {
        [$owner, $member, $task] = $this->context();
        Event::fake([UserRealtimeEvent::class]);
        $this->actingAs($owner)->postJson("/api/tasks/{$task->id}/assignees", ['user_id' => $member->id])->assertOk();
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $member->id, 'type' => TaskAssignedNotification::class]);
        Event::assertDispatched(UserRealtimeEvent::class, fn (UserRealtimeEvent $event): bool => $event->action === 'notification.created' && $event->userId === $member->id);
    }

    public function test_notifications_are_private_and_mark_read_is_idempotent(): void
    {
        [$owner, $member, $task] = $this->context();
        $member->notify(new TaskAssignedNotification($task, $owner));
        $notification = $member->notifications()->firstOrFail();
        $this->actingAs($owner)->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count', 0);
        $this->actingAs($member)->patchJson("/api/notifications/{$notification->id}/read")->assertOk();
        $this->actingAs($member)->patchJson("/api/notifications/{$notification->id}/read")->assertOk()->assertJsonPath('unread_count', 0);
    }

    /** @return array{0: User, 1: User, 2: Task} */
    private function context(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Board']);
        $column = BoardColumn::create(['board_id' => $board->id, 'name' => 'Todo', 'position' => 1000]);

        return [$owner, $member, Task::create(['board_id' => $board->id, 'board_column_id' => $column->id, 'title' => 'Task', 'position' => 1000])];
    }
}
