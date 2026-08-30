<?php

namespace Tests\Feature;

use App\Events\ActivityLogged;
use App\Events\TaskCreated;
use App\Events\TaskDeleted;
use App\Events\TaskEditingStateChanged;
use App\Events\TaskMoved;
use App\Events\TasksReordered;
use App\Events\TaskUpdated;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BoardRealtimeTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        Broadcast::purge('reverb');

        require base_path('routes/channels.php');
    }

    public function test_owner_and_member_can_authorize_the_board_channel(): void
    {
        [$owner, $workspace, $board] = $this->sharedBoard();
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);

        $this->actingAs($owner)
            ->postJson('/broadcasting/auth', $this->channelPayload($board))
            ->assertOk();

        $this->actingAs($member)
            ->postJson('/broadcasting/auth', $this->channelPayload($board))
            ->assertOk();

        $this->actingAs($member)
            ->postJson('/broadcasting/auth', $this->presenceChannelPayload($board))
            ->assertOk();
    }

    public function test_owner_can_authorize_board_presence_with_only_safe_user_data(): void
    {
        [$owner, , $board] = $this->sharedBoard();

        $response = $this->actingAs($owner)
            ->postJson('/broadcasting/auth', $this->presenceChannelPayload($board))
            ->assertOk();

        $channelData = json_decode($response->json('channel_data'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame((string) $owner->id, $channelData['user_id']);
        $this->assertSame(['id', 'name'], array_keys($channelData['user_info']));
        $this->assertSame($owner->id, $channelData['user_info']['id']);
        $this->assertSame($owner->name, $channelData['user_info']['name']);
        $this->assertArrayNotHasKey('email', $channelData['user_info']);
    }

    public function test_external_users_cannot_authorize_the_board_channel(): void
    {
        [$owner, , $board] = $this->sharedBoard();
        $external = User::factory()->create();

        $this->actingAs($external)
            ->postJson('/broadcasting/auth', $this->channelPayload($board))
            ->assertForbidden();

        $this->actingAs($external)
            ->postJson('/broadcasting/auth', $this->presenceChannelPayload($board))
            ->assertForbidden();

        $otherWorkspaceOwner = User::factory()->create();
        $otherWorkspace = Workspace::create([
            'owner_id' => $otherWorkspaceOwner->id,
            'name' => 'Altro workspace',
            'type' => 'shared',
        ]);
        $otherWorkspace->members()->attach($otherWorkspaceOwner->id, ['role' => 'owner', 'joined_at' => now()]);
        $otherBoard = Board::create(['workspace_id' => $otherWorkspace->id, 'name' => 'Altro board']);

        $this->actingAs($owner)
            ->postJson('/broadcasting/auth', $this->channelPayload($otherBoard))
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson('/broadcasting/auth', $this->presenceChannelPayload($otherBoard))
            ->assertForbidden();
    }

    public function test_create_task_dispatches_a_compact_created_event_on_its_board(): void
    {
        [$user, , $board, $column] = $this->sharedBoard();
        $createdEvent = null;
        Event::listen(TaskCreated::class, function (TaskCreated $event) use (&$createdEvent): void {
            $createdEvent = $event;
        });
        Event::fake([ActivityLogged::class]);
        config(['broadcasting.default' => 'null']);

        DB::beginTransaction();
        $this->actingAs($user)
            ->postJson("/api/boards/{$board->id}/columns/{$column->id}/tasks", [
                'title' => 'Nuova task',
                'description' => 'Descrizione',
                'priority' => 'high',
            ])
            ->assertCreated();
        DB::commit();

        $this->assertInstanceOf(TaskCreated::class, $createdEvent);
        $this->assertSame("private-board.{$board->id}", $createdEvent->broadcastOn()[0]->name);
        $this->assertSame(['task'], array_keys($createdEvent->broadcastWith()));
        $this->assertSame([
            'id',
            'board_id',
            'board_column_id',
            'category_id',
            'title',
            'color',
            'description',
            'position',
            'priority',
            'due_at',
            'archived',
        ], array_keys($createdEvent->broadcastWith()['task']));
    }

    public function test_effective_task_update_dispatches_but_an_unchanged_update_does_not(): void
    {
        [$user, , $board, $column] = $this->sharedBoard();
        $task = $this->task($board, $column, 'Prima');
        Event::fake([
            TaskUpdated::class,
            ActivityLogged::class,
        ]);

        DB::beginTransaction();
        $this->actingAs($user)
            ->patchJson("/api/tasks/{$task->id}", [
                'title' => 'Dopo',
                'description' => null,
                'priority' => null,
                'due_at' => null,
                'category_id' => null,
            ])
            ->assertOk();
        DB::commit();

        Event::assertDispatched(TaskUpdated::class);
        Event::assertDispatched(ActivityLogged::class);

        Event::fake([
            TaskUpdated::class,
            ActivityLogged::class,
        ]);
        DB::beginTransaction();
        $this->actingAs($user)
            ->patchJson("/api/tasks/{$task->id}", [
                'title' => 'Dopo',
                'description' => null,
                'priority' => null,
                'due_at' => null,
                'category_id' => null,
            ])
            ->assertOk();
        DB::commit();

        Event::assertNotDispatched(TaskUpdated::class);
        Event::assertNotDispatched(ActivityLogged::class);
    }

    public function test_move_task_dispatches_the_event_for_the_correct_board(): void
    {
        [$user, , $board, $source] = $this->sharedBoard();
        $target = BoardColumn::create(['board_id' => $board->id, 'name' => 'Doing', 'position' => 2000]);
        $task = $this->task($board, $source, 'Sposta');
        Event::fake([
            TaskMoved::class,
            ActivityLogged::class,
        ]);

        $this->actingAs($user)
            ->postJson("/api/tasks/{$task->id}/move", [
                'target_column_id' => $target->id,
                'position' => 1000,
            ])
            ->assertOk();

        Event::assertDispatched(TaskMoved::class, function (TaskMoved $event) use ($board, $target): bool {
            return $event->task['board_id'] === $board->id
                && $event->task['board_column_id'] === $target->id;
        });
    }

    public function test_delete_task_dispatches_only_its_identifiers_and_does_not_duplicate_activity(): void
    {
        [$user, $workspace, $board, $column] = $this->sharedBoard();
        $task = $this->task($board, $column, 'Da eliminare');
        Event::fake([
            TaskDeleted::class,
            ActivityLogged::class,
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertNoContent();

        Event::assertDispatched(TaskDeleted::class, function (TaskDeleted $event) use ($task, $board): bool {
            return $event->taskId === $task->id
                && $event->boardId === $board->id
                && array_keys($event->broadcastWith()) === ['task_id', 'board_id'];
        });
        $this->assertSame(1, $workspace->activityLogs()->where('action', 'task.deleted')->count());
    }

    public function test_reordering_tasks_dispatches_one_reorder_event_without_activity(): void
    {
        [$user, $workspace, $board, $column] = $this->sharedBoard();
        $first = $this->task($board, $column, 'Uno', 1000);
        $second = $this->task($board, $column, 'Due', 2000);
        Event::fake([TasksReordered::class]);

        $this->actingAs($user)
            ->postJson("/api/columns/{$column->id}/tasks/reorder", [
                'task_ids' => [$second->id, $first->id],
            ])
            ->assertOk();

        Event::assertDispatched(TasksReordered::class, function (TasksReordered $event) use ($board, $column, $first, $second): bool {
            return $event->boardId === $board->id
                && $event->columnId === $column->id
                && $event->tasks === [
                    ['id' => $second->id, 'position' => 1000],
                    ['id' => $first->id, 'position' => 2000],
                ];
        });
        $this->assertDatabaseMissing('activity_logs', ['action' => 'task.moved']);
    }

    public function test_editing_state_is_broadcast_with_authenticated_user_payload_without_database_changes(): void
    {
        [$owner, $workspace, $board, $column] = $this->sharedBoard();
        $task = $this->task($board, $column, 'Modifica');
        $updatedAt = $task->updated_at;
        Event::fake([TaskEditingStateChanged::class]);

        $this->actingAs($owner)
            ->postJson("/api/tasks/{$task->id}/editing-state", [
                'active' => true,
                'session_id' => '11111111-1111-4111-8111-111111111111',
                'user_id' => 999,
                'username' => 'spoofed',
            ])->assertOk();

        Event::assertDispatched(TaskEditingStateChanged::class, function (TaskEditingStateChanged $event) use ($owner, $board, $task): bool {
            $payload = $event->broadcastWith();

            return $event->active
                && $event->sessionId === '11111111-1111-4111-8111-111111111111'
                && $payload['board_id'] === $board->id
                && $payload['task_id'] === $task->id
                && array_keys($payload['user']) === ['id', 'name', 'last_name', 'username']
                && $payload['user']['id'] === $owner->id
                && ! array_key_exists('email', $payload['user']);
        });
        $this->assertSame($updatedAt?->toISOString(), $task->fresh()->updated_at?->toISOString());
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertTrue($workspace->hasMember($owner));
    }

    public function test_editing_state_allows_member_and_stop_state(): void
    {
        [$owner, $workspace, $board, $column] = $this->sharedBoard();
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $task = $this->task($board, $column, 'Modifica');
        Event::fake([TaskEditingStateChanged::class]);

        $this->actingAs($member)
            ->postJson("/api/tasks/{$task->id}/editing-state", [
                'active' => false,
                'session_id' => '22222222-2222-4222-8222-222222222222',
            ])->assertOk();

        Event::assertDispatched(TaskEditingStateChanged::class, fn (TaskEditingStateChanged $event): bool => ! $event->active);
    }

    public function test_viewer_and_outsider_cannot_signal_active_editing(): void
    {
        [$owner, $workspace, $board, $column] = $this->sharedBoard();
        $viewer = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace->members()->attach($viewer->id, ['role' => 'viewer', 'joined_at' => now()]);
        $task = $this->task($board, $column, 'Protetta');
        Event::fake([TaskEditingStateChanged::class]);

        foreach ([$viewer, $outsider] as $user) {
            $this->actingAs($user)
                ->postJson("/api/tasks/{$task->id}/editing-state", [
                    'active' => true,
                    'session_id' => '33333333-3333-4333-8333-333333333333',
                ])->assertForbidden();
        }

        Event::assertNotDispatched(TaskEditingStateChanged::class);
        $this->assertTrue($workspace->hasMember($owner));
    }

    /**
     * @return array{0: User, 1: Workspace, 2: Board, 3: BoardColumn}
     */
    private function sharedBoard(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $owner->id,
            'name' => 'Team',
            'type' => 'shared',
        ]);
        $workspace->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Board']);
        $column = BoardColumn::create(['board_id' => $board->id, 'name' => 'To do', 'position' => 1000]);

        return [$owner, $workspace, $board, $column];
    }

    private function task(Board $board, BoardColumn $column, string $title, int $position = 1000): Task
    {
        return Task::create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'title' => $title,
            'position' => $position,
        ]);
    }

    /** @return array{socket_id: string, channel_name: string} */
    private function channelPayload(Board $board): array
    {
        return [
            'socket_id' => '1234.5678',
            'channel_name' => "private-board.{$board->id}",
        ];
    }

    /** @return array{socket_id: string, channel_name: string} */
    private function presenceChannelPayload(Board $board): array
    {
        return [
            'socket_id' => '1234.5678',
            'channel_name' => "presence-board-presence.{$board->id}",
        ];
    }
}
