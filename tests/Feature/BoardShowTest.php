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

class BoardShowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_member_can_load_complete_board(): void
    {
        $user = User::create([
            'name' => 'Mario',
            'email' => 'mario@example.com',
            'password' => bcrypt('password'),
        ]);

        $workspace = $user->ownedWorkspaces()
            ->where('type', 'personal')
            ->firstOrFail();

        $board = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Progetto Test',
        ]);

        $column = BoardColumn::create([
            'board_id' => $board->id,
            'name' => 'Da fare',
            'position' => 1000,
        ]);

        $category = Category::create([
            'board_id' => $board->id,
            'name' => 'Backend',
            'position' => 1000,
        ]);

        Task::create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'category_id' => $category->id,
            'title' => 'Creare API',
            'position' => 1000,
        ]);

        $this->actingAs($user);

        $this->getJson("/api/boards/{$board->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Progetto Test')
            ->assertJsonPath('data.columns.0.name', 'Da fare')
            ->assertJsonPath(
                'data.columns.0.tasks.0.title',
                'Creare API'
            )
            ->assertJsonPath(
                'data.categories.0.name',
                'Backend'
            );
    }

    public function test_user_cannot_load_board_from_another_workspace(): void
    {
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $otherUser = User::create([
            'name' => 'Altro',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);

        $workspace = $owner->ownedWorkspaces()
            ->where('type', 'personal')
            ->firstOrFail();

        $board = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Board privata',
        ]);

        $this->actingAs($otherUser);

        $this->getJson("/api/boards/{$board->id}")
            ->assertForbidden();
    }

    public function test_archived_tasks_are_not_returned(): void
    {
        $user = User::create([
            'name' => 'Mario',
            'email' => 'mario2@example.com',
            'password' => bcrypt('password'),
        ]);

        $workspace = $user->ownedWorkspaces()
            ->where('type', 'personal')
            ->firstOrFail();

        $board = Board::create([
            'workspace_id' => $workspace->id,
            'name' => 'Board',
        ]);

        $column = BoardColumn::create([
            'board_id' => $board->id,
            'name' => 'Da fare',
            'position' => 1000,
        ]);

        Task::create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'title' => 'Task visibile',
            'position' => 1000,
            'archived' => false,
        ]);

        Task::create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            'title' => 'Task archiviata',
            'position' => 2000,
            'archived' => true,
        ]);

        $this->actingAs($user);

        $response = $this->getJson("/api/boards/{$board->id}")
            ->assertOk();

        $response->assertJsonFragment([
            'title' => 'Task visibile',
        ]);

        $response->assertJsonMissing([
            'title' => 'Task archiviata',
        ]);
    }
}
