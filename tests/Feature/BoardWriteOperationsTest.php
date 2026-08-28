<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardWriteOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_user_can_create_update_and_delete_task(): void
    {
        [$user, $board, $column] = $this->boardWithColumn();
        $category = Category::create([
            'board_id' => $board->id,
            'name' => 'Backend',
            'color' => '#4f6f9f',
            'position' => 1000,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/boards/{$board->id}/columns/{$column->id}/tasks", [
                'title' => 'Preparare preventivo',
                'description' => 'Bozza iniziale',
                'category_id' => $category->id,
                'priority' => 'high',
                'due_at' => '2026-09-10',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Preparare preventivo')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.category_id', $category->id);

        $task = Task::findOrFail($response->json('data.id'));
        $this->assertSame('2026-09-10', $task->due_at->toDateString());

        $this->actingAs($user)
            ->patchJson("/api/tasks/{$task->id}", [
                'title' => 'Preparare offerta',
                'description' => 'Versione finale',
                'category_id' => null,
                'priority' => 'medium',
                'due_at' => '2026-09-12',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Preparare offerta')
            ->assertJsonPath('data.priority', 'medium')
            ->assertJsonPath('data.category_id', null);

        $this->actingAs($user)
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_user_can_drag_task_between_columns(): void
    {
        [$user, $board, $source] = $this->boardWithColumn();
        $target = BoardColumn::create(['board_id' => $board->id, 'name' => 'Doing', 'position' => 2000]);
        $task = Task::create([
            'board_id' => $board->id,
            'board_column_id' => $source->id,
            'title' => 'Move me',
            'position' => 1000,
        ]);

        $this->actingAs($user)
            ->postJson("/api/tasks/{$task->id}/move", [
                'target_column_id' => $target->id,
                'position' => 1000,
            ])
            ->assertOk()
            ->assertJsonPath('data.board_column_id', $target->id)
            ->assertJsonPath('data.position', 1000);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'board_column_id' => $target->id,
            'position' => 1000,
        ]);
    }

    public function test_user_can_reorder_tasks_in_column(): void
    {
        [$user, , $column] = $this->boardWithColumn();
        $first = Task::create(['board_id' => $column->board_id, 'board_column_id' => $column->id, 'title' => 'A', 'position' => 1000]);
        $second = Task::create(['board_id' => $column->board_id, 'board_column_id' => $column->id, 'title' => 'B', 'position' => 2000]);
        $third = Task::create(['board_id' => $column->board_id, 'board_column_id' => $column->id, 'title' => 'C', 'position' => 3000]);

        $this->actingAs($user)
            ->postJson("/api/columns/{$column->id}/tasks/reorder", [
                'task_ids' => [$third->id, $first->id, $second->id],
            ])
            ->assertOk();

        $this->assertSame(1000, $third->fresh()->position);
        $this->assertSame(2000, $first->fresh()->position);
        $this->assertSame(3000, $second->fresh()->position);
    }

    public function test_user_can_create_update_and_delete_categories(): void
    {
        [$user, $board, $column] = $this->boardWithColumn();

        $response = $this->actingAs($user)
            ->postJson("/api/boards/{$board->id}/categories", [
                'name' => 'Design',
                'color' => '#4f6f9f',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Design')
            ->assertJsonPath('data.color', '#4f6f9f');

        $category = Category::findOrFail($response->json('data.id'));
        $task = Task::create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'category_id' => $category->id,
            'title' => 'Wireframe',
            'position' => 1000,
        ]);

        $this->actingAs($user)
            ->patchJson("/api/categories/{$category->id}", [
                'name' => 'UI',
                'color' => '#5f8fa6',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'UI')
            ->assertJsonPath('data.color', '#5f8fa6');

        $this->actingAs($user)
            ->deleteJson("/api/categories/{$category->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertNull($task->fresh()->category_id);
    }

    public function test_user_can_create_update_reorder_and_delete_columns(): void
    {
        [$user, $board, $first] = $this->boardWithColumn();

        $response = $this->actingAs($user)
            ->postJson("/api/boards/{$board->id}/columns", ['name' => 'Review'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Review');

        $second = BoardColumn::findOrFail($response->json('data.id'));

        $this->actingAs($user)
            ->patchJson("/api/columns/{$second->id}", ['name' => 'In review'])
            ->assertOk()
            ->assertJsonPath('data.name', 'In review');

        $this->actingAs($user)
            ->postJson("/api/boards/{$board->id}/columns/reorder", [
                'column_ids' => [$second->id, $first->id],
            ])
            ->assertOk();

        $this->assertSame(1000, $second->fresh()->position);
        $this->assertSame(2000, $first->fresh()->position);

        $this->actingAs($user)
            ->deleteJson("/api/columns/{$second->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('board_columns', ['id' => $second->id]);
    }

    public function test_column_with_tasks_cannot_be_deleted(): void
    {
        [$user, $board, $column] = $this->boardWithColumn();
        BoardColumn::create(['board_id' => $board->id, 'name' => 'Other', 'position' => 2000]);
        Task::create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'title' => 'Existing',
            'position' => 1000,
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/columns/{$column->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('column');
    }

    public function test_cross_workspace_board_operations_are_denied(): void
    {
        [$owner, $board, $column] = $this->boardWithColumn();
        $outsider = User::factory()->create();
        $category = Category::create(['board_id' => $board->id, 'name' => 'Private', 'position' => 1000]);
        $task = Task::create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'category_id' => $category->id,
            'title' => 'Private task',
            'position' => 1000,
        ]);

        $this->actingAs($outsider)
            ->postJson("/api/boards/{$board->id}/columns/{$column->id}/tasks", ['title' => 'No'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('board');

        $this->actingAs($outsider)
            ->patchJson("/api/tasks/{$task->id}", ['title' => 'No'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('task');

        $this->actingAs($outsider)
            ->postJson("/api/boards/{$board->id}/categories", ['name' => 'No'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('board');

        $this->actingAs($outsider)
            ->patchJson("/api/categories/{$category->id}", ['name' => 'No'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category');

        $this->actingAs($outsider)
            ->patchJson("/api/columns/{$column->id}", ['name' => 'No'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('column');

        $this->assertTrue($owner->isMemberOf($board->workspace));
    }

    /** @return array{User, Board, BoardColumn} */
    private function boardWithColumn(): array
    {
        $user = User::factory()->create();
        $workspace = $user->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Board']);
        $column = BoardColumn::create(['board_id' => $board->id, 'name' => 'To do', 'position' => 1000]);

        return [$user, $board, $column];
    }
}
