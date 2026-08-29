<?php

namespace Tests\Feature;

use App\Actions\Activity\LogActivity;
use App\Models\Board;
use App\Models\Folder;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_member_can_read_workspace_activity_and_non_member_cannot(): void
    {
        $owner = $this->user();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Team', 'type' => 'shared']);
        $workspace->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);
        app(LogActivity::class)->execute($owner, $workspace, 'workspace.created', null, $workspace, ['workspace_name' => $workspace->name]);
        $external = $this->user();

        $this->actingAs($owner)->getJson("/api/workspaces/{$workspace->id}/activity")->assertOk()->assertJsonPath('data.0.action', 'workspace.created');
        $this->actingAs($external)->getJson("/api/workspaces/{$workspace->id}/activity")->assertForbidden();
    }

    public function test_board_filter_is_scoped_to_workspace(): void
    {
        $owner = $this->user();
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Team', 'type' => 'shared']);
        $workspace->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Board']);
        app(LogActivity::class)->execute($owner, $workspace, 'board.created', $board, $board, ['board_name' => $board->name]);

        $this->actingAs($owner)->getJson("/api/workspaces/{$workspace->id}/activity?board_id={$board->id}")->assertOk()->assertJsonPath('data.0.board.id', $board->id);
    }

    public function test_move_board_logs_only_when_folder_changes(): void
    {
        $owner = $this->user();
        $workspace = $this->workspace($owner);
        $from = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Clienti']);
        $to = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Archivio']);
        $board = Board::create(['workspace_id' => $workspace->id, 'folder_id' => $from->id, 'name' => 'Sito']);

        $this->actingAs($owner)->postJson("/api/boards/{$board->id}/move", ['folder_id' => $to->id])->assertOk();
        $log = $workspace->activityLogs()->where('action', 'board.moved')->firstOrFail();
        $this->assertEquals([
            'board_name' => 'Sito',
            'from_folder_id' => $from->id,
            'from_folder_name' => 'Clienti',
            'to_folder_id' => $to->id,
            'to_folder_name' => 'Archivio',
        ], $log->metadata);

        $this->actingAs($owner)->postJson("/api/boards/{$board->id}/move", ['folder_id' => $to->id])->assertOk();
        $this->assertSame(1, $workspace->activityLogs()->where('action', 'board.moved')->count());
    }

    public function test_move_folder_logs_only_when_parent_changes(): void
    {
        $owner = $this->user();
        $workspace = $this->workspace($owner);
        $from = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Origine']);
        $to = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Destinazione']);
        $folder = Folder::create(['workspace_id' => $workspace->id, 'parent_id' => $from->id, 'name' => 'Progetti']);

        $this->actingAs($owner)->postJson("/api/folders/{$folder->id}/move", ['parent_id' => $to->id])->assertOk();
        $log = $workspace->activityLogs()->where('action', 'folder.moved')->firstOrFail();
        $this->assertSame('Progetti', $log->metadata['folder_name']);
        $this->assertSame('Origine', $log->metadata['from_parent_name']);
        $this->assertSame('Destinazione', $log->metadata['to_parent_name']);

        $this->actingAs($owner)->postJson("/api/folders/{$folder->id}/move", ['parent_id' => $to->id])->assertOk();
        $this->assertSame(1, $workspace->activityLogs()->where('action', 'folder.moved')->count());
    }

    private function workspace(User $owner): Workspace
    {
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Team', 'type' => 'shared']);
        $workspace->members()->attach($owner->id, ['role' => 'owner', 'joined_at' => now()]);

        return $workspace;
    }

    private function user(): User
    {
        $user = User::factory()->create();
        $user->subscription()->update(['plan_id' => Plan::where('slug', 'team')->value('id')]);

        return $user;
    }
}
