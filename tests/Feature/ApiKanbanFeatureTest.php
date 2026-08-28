<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKanbanFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_authenticated_user_can_create_a_shared_workspace(): void
    {
        $user = User::factory()->create();
        $user->subscription()->update(['plan_id' => Plan::where('slug', 'team')->value('id')]);

        $response = $this->actingAs($user)->postJson('/api/workspaces', ['name' => 'Product']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Product')
            ->assertJsonPath('data.type', 'shared');
        $this->assertDatabaseHas('workspaces', ['name' => 'Product', 'owner_id' => $user->id]);
    }

    public function test_user_cannot_create_a_folder_in_another_users_workspace(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Private', 'type' => 'shared']);
        $workspace->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);

        $this->actingAs($outsider)
            ->postJson("/api/workspaces/{$workspace->id}/folders", ['name' => 'No access'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('workspace');
    }

    public function test_nested_binding_rejects_a_column_from_another_board(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'Shared', 'type' => 'shared']);
        $workspace->members()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Board A']);
        $otherBoard = Board::create(['workspace_id' => $workspace->id, 'name' => 'Board B']);
        $foreignColumn = BoardColumn::create(['board_id' => $otherBoard->id, 'name' => 'Doing', 'position' => 1000]);

        $this->actingAs($user)
            ->postJson("/api/boards/{$board->id}/columns/{$foreignColumn->id}/tasks", ['title' => 'Invalid'])
            ->assertNotFound();
    }

    public function test_reorder_endpoint_requires_a_complete_column_list(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'Shared', 'type' => 'shared']);
        $workspace->members()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Board']);
        BoardColumn::create(['board_id' => $board->id, 'name' => 'To do', 'position' => 1000]);

        $this->actingAs($user)
            ->postJson("/api/boards/{$board->id}/columns/reorder", ['column_ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('column_ids');
    }
}
