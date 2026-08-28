<?php

namespace Tests\Feature;

use App\Models\Board;
use App\Models\Folder;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderBoardWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_member_can_create_rename_move_and_archive_folder_and_board(): void
    {
        $user = User::create([
            'name' => 'Mario',
            'email' => 'mario@example.com',
            'password' => bcrypt('password'),
        ]);
        $workspace = $user->ownedWorkspaces()->where('type', 'personal')->firstOrFail();

        $folderResponse = $this->actingAs($user)->postJson(
            "/api/workspaces/{$workspace->id}/folders",
            ['name' => 'Clienti', 'color' => '#4f6f9f']
        )->assertCreated();

        $folder = Folder::findOrFail($folderResponse->json('data.id'));
        $this->assertSame('#4f6f9f', $folder->color);
        $subfolder = Folder::create([
            'workspace_id' => $workspace->id,
            'parent_id' => $folder->id,
            'name' => 'Attivi',
        ]);

        $this->actingAs($user)
            ->patchJson("/api/folders/{$subfolder->id}", ['name' => 'In corso'])
            ->assertOk()
            ->assertJsonPath('data.name', 'In corso');

        $this->actingAs($user)
            ->postJson("/api/folders/{$subfolder->id}/move", ['parent_id' => null])
            ->assertOk()
            ->assertJsonPath('data.parent_id', null);

        $boardResponse = $this->actingAs($user)->postJson(
            "/api/workspaces/{$workspace->id}/boards",
            ['name' => 'Roadmap', 'folder_id' => $folder->id]
        )->assertCreated();

        $board = Board::findOrFail($boardResponse->json('data.id'));

        $this->actingAs($user)
            ->patchJson("/api/boards/{$board->id}", ['name' => 'Roadmap 2026'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Roadmap 2026');

        $this->actingAs($user)
            ->postJson("/api/boards/{$board->id}/move", ['folder_id' => null])
            ->assertOk()
            ->assertJsonPath('data.folder_id', null);

        $this->actingAs($user)
            ->postJson("/api/boards/{$board->id}/archive", ['archived' => true])
            ->assertOk()
            ->assertJsonPath('data.archived', true);

        $this->actingAs($user)
            ->postJson("/api/boards/{$board->id}/archive", ['archived' => false])
            ->assertOk()
            ->assertJsonPath('data.archived', false);

        $this->actingAs($user)
            ->postJson("/api/folders/{$folder->id}/archive", ['archived' => true])
            ->assertOk()
            ->assertJsonPath('data.archived', true);

        $this->actingAs($user)
            ->postJson("/api/folders/{$folder->id}/archive", ['archived' => false])
            ->assertOk()
            ->assertJsonPath('data.archived', false);
    }

    public function test_non_member_cannot_modify_folder_or_board(): void
    {
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);
        $outsider = User::create([
            'name' => 'Outsider',
            'email' => 'outsider@example.com',
            'password' => bcrypt('password'),
        ]);
        $workspace = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Privata']);
        $board = Board::create(['workspace_id' => $workspace->id, 'folder_id' => $folder->id, 'name' => 'Privata']);

        $this->actingAs($outsider)
            ->patchJson("/api/folders/{$folder->id}", ['name' => 'Violata'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('folder');

        $this->actingAs($outsider)
            ->patchJson("/api/boards/{$board->id}", ['name' => 'Violata'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('board');
    }

    public function test_resources_cannot_be_moved_to_another_workspace(): void
    {
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner2@example.com',
            'password' => bcrypt('password'),
        ]);
        $otherOwner = User::create([
            'name' => 'Other',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
        ]);
        $workspace = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $otherWorkspace = $otherOwner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Locale']);
        $otherFolder = Folder::create(['workspace_id' => $otherWorkspace->id, 'name' => 'Estera']);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Board']);

        $this->actingAs($owner)
            ->postJson("/api/folders/{$folder->id}/move", ['parent_id' => $otherFolder->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent');

        $this->actingAs($owner)
            ->postJson("/api/boards/{$board->id}/move", ['folder_id' => $otherFolder->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('folder');
    }

    public function test_board_move_can_update_archive_state_like_v0_location(): void
    {
        $user = User::create([
            'name' => 'Mario',
            'email' => 'board-location@example.com',
            'password' => bcrypt('password'),
        ]);
        $workspace = $user->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Current']);
        $board = Board::create(['workspace_id' => $workspace->id, 'folder_id' => $folder->id, 'name' => 'Board']);

        $this->actingAs($user)
            ->postJson("/api/boards/{$board->id}/move", ['folder_id' => null, 'archived' => true])
            ->assertOk()
            ->assertJsonPath('data.folder_id', null)
            ->assertJsonPath('data.archived', true);

        $this->actingAs($user)
            ->postJson("/api/boards/{$board->id}/move", ['folder_id' => $folder->id, 'archived' => false])
            ->assertOk()
            ->assertJsonPath('data.folder_id', $folder->id)
            ->assertJsonPath('data.archived', false);
    }

    public function test_archived_nested_folder_becomes_archive_tree_root_like_v0(): void
    {
        $user = User::create([
            'name' => 'Mario',
            'email' => 'archive@example.com',
            'password' => bcrypt('password'),
        ]);
        $workspace = $user->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $parent = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Parent']);
        $child = Folder::create(['workspace_id' => $workspace->id, 'parent_id' => $parent->id, 'name' => 'Child']);

        $this->actingAs($user)
            ->postJson("/api/folders/{$child->id}/archive", ['archived' => true])
            ->assertOk()
            ->assertJsonPath('data.parent_id', null)
            ->assertJsonPath('data.archived', true);

        $response = $this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->id}/folders")
            ->assertOk();

        $this->assertTrue(collect($response->json('data'))->contains(
            fn (array $folder): bool => $folder['id'] === $child->id
                && $folder['parent_id'] === null
                && $folder['archived'] === true
        ));
    }

    public function test_folder_archive_recursively_archives_subfolders_and_contained_boards(): void
    {
        $user = User::create([
            'name' => 'Mario',
            'email' => 'recursive-archive@example.com',
            'password' => bcrypt('password'),
        ]);
        $workspace = $user->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $parent = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Parent']);
        $child = Folder::create(['workspace_id' => $workspace->id, 'parent_id' => $parent->id, 'name' => 'Child']);
        $grandchild = Folder::create(['workspace_id' => $workspace->id, 'parent_id' => $child->id, 'name' => 'Grandchild']);
        $outside = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Outside']);

        $parentBoard = Board::create(['workspace_id' => $workspace->id, 'folder_id' => $parent->id, 'name' => 'Parent board']);
        $childBoard = Board::create(['workspace_id' => $workspace->id, 'folder_id' => $child->id, 'name' => 'Child board']);
        $grandchildBoard = Board::create(['workspace_id' => $workspace->id, 'folder_id' => $grandchild->id, 'name' => 'Grandchild board']);
        $outsideBoard = Board::create(['workspace_id' => $workspace->id, 'folder_id' => $outside->id, 'name' => 'Outside board']);

        $this->actingAs($user)
            ->postJson("/api/folders/{$parent->id}/archive", ['archived' => true])
            ->assertOk()
            ->assertJsonPath('data.archived', true);

        $this->assertTrue($parent->fresh()->archived);
        $this->assertTrue($child->fresh()->archived);
        $this->assertTrue($grandchild->fresh()->archived);
        $this->assertFalse($outside->fresh()->archived);

        $this->assertTrue($parentBoard->fresh()->archived);
        $this->assertTrue($childBoard->fresh()->archived);
        $this->assertTrue($grandchildBoard->fresh()->archived);
        $this->assertFalse($outsideBoard->fresh()->archived);

        $this->assertSame($parent->id, $child->fresh()->parent_id);
        $this->assertSame($child->id, $grandchild->fresh()->parent_id);
        $this->assertSame($grandchild->id, $grandchildBoard->fresh()->folder_id);
    }

    public function test_archived_tree_is_returned_with_parent_and_folder_ids_for_navigation(): void
    {
        $user = User::create([
            'name' => 'Mario',
            'email' => 'archive-navigation@example.com',
            'password' => bcrypt('password'),
        ]);
        $workspace = $user->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $parent = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Parent']);
        $child = Folder::create(['workspace_id' => $workspace->id, 'parent_id' => $parent->id, 'name' => 'Child']);
        $board = Board::create(['workspace_id' => $workspace->id, 'folder_id' => $child->id, 'name' => 'Child board']);

        $this->actingAs($user)
            ->postJson("/api/folders/{$parent->id}/archive", ['archived' => true])
            ->assertOk();

        $folders = collect($this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->id}/folders")
            ->assertOk()
            ->json('data'));
        $boards = collect($this->actingAs($user)
            ->getJson("/api/workspaces/{$workspace->id}/boards")
            ->assertOk()
            ->json('data'));

        $this->assertTrue($folders->contains(
            fn (array $folder): bool => $folder['id'] === $parent->id
                && $folder['parent_id'] === null
                && $folder['archived'] === true
        ));
        $this->assertTrue($folders->contains(
            fn (array $folder): bool => $folder['id'] === $child->id
                && $folder['parent_id'] === $parent->id
                && $folder['archived'] === true
        ));
        $this->assertTrue($boards->contains(
            fn (array $item): bool => $item['id'] === $board->id
                && $item['folder_id'] === $child->id
                && $item['archived'] === true
        ));
    }

    public function test_folder_restore_recursively_restores_subfolders_and_contained_boards_like_v0(): void
    {
        $user = User::create([
            'name' => 'Mario',
            'email' => 'recursive-restore@example.com',
            'password' => bcrypt('password'),
        ]);
        $workspace = $user->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $parent = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Parent', 'archived' => true]);
        $child = Folder::create([
            'workspace_id' => $workspace->id,
            'parent_id' => $parent->id,
            'name' => 'Child',
            'archived' => true,
        ]);
        $board = Board::create([
            'workspace_id' => $workspace->id,
            'folder_id' => $child->id,
            'name' => 'Child board',
            'archived' => true,
        ]);

        $this->actingAs($user)
            ->postJson("/api/folders/{$parent->id}/archive", ['archived' => false])
            ->assertOk()
            ->assertJsonPath('data.parent_id', null)
            ->assertJsonPath('data.archived', false);

        $this->assertFalse($parent->fresh()->archived);
        $this->assertFalse($child->fresh()->archived);
        $this->assertFalse($board->fresh()->archived);
        $this->assertSame($parent->id, $child->fresh()->parent_id);
        $this->assertSame($child->id, $board->fresh()->folder_id);
    }

    public function test_moving_folder_into_archived_folder_applies_archive_state_to_its_tree(): void
    {
        $user = User::create([
            'name' => 'Mario',
            'email' => 'move-archive-state@example.com',
            'password' => bcrypt('password'),
        ]);
        $workspace = $user->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $archiveParent = Folder::create([
            'workspace_id' => $workspace->id,
            'name' => 'Archive root',
            'archived' => true,
        ]);
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Folder']);
        $child = Folder::create(['workspace_id' => $workspace->id, 'parent_id' => $folder->id, 'name' => 'Child']);
        $board = Board::create(['workspace_id' => $workspace->id, 'folder_id' => $child->id, 'name' => 'Child board']);

        $this->actingAs($user)
            ->postJson("/api/folders/{$folder->id}/move", ['parent_id' => $archiveParent->id])
            ->assertOk()
            ->assertJsonPath('data.parent_id', $archiveParent->id)
            ->assertJsonPath('data.archived', true);

        $this->assertTrue($folder->fresh()->archived);
        $this->assertTrue($child->fresh()->archived);
        $this->assertTrue($board->fresh()->archived);
    }

    public function test_board_creation_uses_the_requested_folder_or_root_and_persists_color(): void
    {
        $user = User::create([
            'name' => 'Mario',
            'email' => 'colors@example.com',
            'password' => bcrypt('password'),
        ]);
        $workspace = $user->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Current']);

        $this->actingAs($user)
            ->postJson("/api/workspaces/{$workspace->id}/boards", [
                'name' => 'In cartella',
                'folder_id' => $folder->id,
                'color' => '#d96060',
            ])
            ->assertCreated()
            ->assertJsonPath('data.folder_id', $folder->id)
            ->assertJsonPath('data.color', '#d96060');

        $this->actingAs($user)
            ->postJson("/api/workspaces/{$workspace->id}/boards", [
                'name' => 'In root',
                'folder_id' => null,
                'color' => '#2563eb',
            ])
            ->assertCreated()
            ->assertJsonPath('data.folder_id', null)
            ->assertJsonPath('data.color', '#2563eb');
    }
}
