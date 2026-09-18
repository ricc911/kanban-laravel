<?php

namespace Tests\Feature;

use App\Actions\Tasks\SendTaskDueReminders;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDueSoonNotification;
use App\Notifications\TaskOverdueNotification;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDueReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_archived_board_does_not_send_due_reminders_and_restored_board_can_send_them(): void
    {
        $this->travelTo(now()->startOfDay());
        $owner = User::factory()->create();
        $workspace = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $activeBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Attiva']);
        $archivedBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Archiviata']);
        $activeColumn = BoardColumn::create(['board_id' => $activeBoard->id, 'name' => 'To do', 'position' => 1000]);
        $archivedColumn = BoardColumn::create(['board_id' => $archivedBoard->id, 'name' => 'To do', 'position' => 1000]);
        $activeTask = Task::create(['board_id' => $activeBoard->id, 'board_column_id' => $activeColumn->id, 'title' => 'Attiva', 'due_at' => now()->addHours(12), 'position' => 1000]);
        $archivedTask = Task::create(['board_id' => $archivedBoard->id, 'board_column_id' => $archivedColumn->id, 'title' => 'Archiviata', 'due_at' => now()->subHour(), 'position' => 1000]);
        $activeTask->assignees()->attach($owner->id);
        $archivedTask->assignees()->attach($owner->id);
        $this->actingAs($owner)->postJson("/api/boards/{$archivedBoard->id}/archive", ['archived' => true])->assertOk();

        $counts = app(SendTaskDueReminders::class)->execute();

        $this->assertSame(['due_soon' => 1, 'overdue' => 0], $counts);
        $this->assertDatabaseHas('task_reminder_deliveries', ['task_id' => $activeTask->id, 'user_id' => $owner->id]);
        $this->assertDatabaseMissing('task_reminder_deliveries', ['task_id' => $archivedTask->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id, 'type' => TaskDueSoonNotification::class]);
        $this->assertSame(1, $owner->notifications()->count());

        $this->actingAs($owner)->postJson("/api/boards/{$archivedBoard->id}/archive", ['archived' => false])->assertOk();
        $restoredCounts = app(SendTaskDueReminders::class)->execute();

        $this->assertSame(['due_soon' => 0, 'overdue' => 1], $restoredCounts);
        $this->assertDatabaseHas('task_reminder_deliveries', ['task_id' => $archivedTask->id, 'user_id' => $owner->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id, 'type' => TaskOverdueNotification::class]);
        $this->assertSame(2, $owner->notifications()->count());
    }
}
