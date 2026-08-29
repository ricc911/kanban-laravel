<?php

namespace Tests\Feature;

use App\Actions\Activity\LogActivity;
use App\Actions\BoardColumns\CreateBoardColumn;
use App\Actions\BoardColumns\DeleteBoardColumn;
use App\Actions\BoardColumns\ReorderBoardColumns;
use App\Actions\BoardColumns\UpdateBoardColumn;
use App\Actions\Boards\ArchiveBoard;
use App\Actions\Boards\CreateBoard;
use App\Actions\Boards\DeleteBoard;
use App\Actions\Boards\MoveBoard;
use App\Actions\Boards\UpdateBoard;
use App\Actions\Categories\CreateCategory;
use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\UpdateCategory;
use App\Actions\Folders\ArchiveFolder;
use App\Actions\Folders\CreateFolder;
use App\Actions\Folders\DeleteFolder;
use App\Actions\Folders\MoveFolder;
use App\Actions\Folders\UpdateFolder;
use App\Actions\Workspaces\AcceptWorkspaceInvitation;
use App\Actions\Workspaces\InviteWorkspaceMember;
use App\Actions\Workspaces\LeaveWorkspace;
use App\Actions\Workspaces\RejectWorkspaceInvitation;
use App\Actions\Workspaces\RemoveWorkspaceMember;
use App\Events\ActivityLogged;
use App\Events\BoardChanged;
use App\Events\UserRealtimeEvent;
use App\Events\WorkspaceChanged;
use App\Models\Board;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DashboardRealtimeTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_members_can_authorize_workspace_and_users_only_their_own_channel(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $outsider = User::factory()->create();

        $this->actingAs($member)->postJson('/broadcasting/auth', $this->channelPayload('workspace', $workspace->id))->assertOk();
        $this->actingAs($outsider)->postJson('/broadcasting/auth', $this->channelPayload('workspace', $workspace->id))->assertForbidden();
        $this->actingAs($owner)->postJson('/broadcasting/auth', $this->channelPayload('user', $owner->id))->assertOk();
        $this->actingAs($member)->postJson('/broadcasting/auth', $this->channelPayload('user', $owner->id))->assertForbidden();
    }

    public function test_folder_operations_emit_workspace_events(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();
        Event::fake();

        $folder = app(CreateFolder::class)->execute($owner, $workspace, 'Clienti');
        Event::assertDispatched(WorkspaceChanged::class, fn (WorkspaceChanged $event): bool => $event->action === 'folder.created');

        app(UpdateFolder::class)->execute($owner, $folder, 'Clienti nuovi');
        Event::assertDispatched(WorkspaceChanged::class, fn (WorkspaceChanged $event): bool => $event->action === 'folder.updated');

        $parent = app(CreateFolder::class)->execute($owner, $workspace, 'Archivio');
        app(MoveFolder::class)->execute($owner, $folder, $parent);
        Event::assertDispatched(WorkspaceChanged::class, fn (WorkspaceChanged $event): bool => $event->action === 'folder.moved');

        app(ArchiveFolder::class)->execute($owner, $folder, true);
        app(ArchiveFolder::class)->execute($owner, $folder, false);
        Event::assertDispatched(WorkspaceChanged::class, fn (WorkspaceChanged $event): bool => $event->action === 'folder.archived');
        Event::assertDispatched(WorkspaceChanged::class, fn (WorkspaceChanged $event): bool => $event->action === 'folder.restored');

        app(DeleteFolder::class)->execute($owner, $folder);
        Event::assertDispatched(WorkspaceChanged::class, fn (WorkspaceChanged $event): bool => $event->action === 'folder.deleted');
    }

    public function test_board_operations_emit_workspace_and_board_events(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();
        Event::fake();

        $board = app(CreateBoard::class)->execute($owner, $workspace, 'Roadmap');
        Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->action === 'board.created' && $event->broadcastToWorkspace);

        app(UpdateBoard::class)->execute($owner, $board, 'Roadmap 2026', 'Descrizione', '#2563eb');
        Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->action === 'board.updated');

        $folder = app(CreateFolder::class)->execute($owner, $workspace, 'Clienti');
        app(MoveBoard::class)->execute($owner, $board, $folder);
        Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->action === 'board.moved');

        app(ArchiveBoard::class)->execute($owner, $board, true);
        app(ArchiveBoard::class)->execute($owner, $board, false);
        Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->action === 'board.archived');
        Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->action === 'board.restored');

        app(DeleteBoard::class)->execute($owner, $board);
        Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->action === 'board.deleted');
    }

    public function test_category_and_column_operations_emit_compact_board_events(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();

        Event::fake([
            BoardChanged::class,
            ActivityLogged::class,
        ]);

        $board = app(CreateBoard::class)->execute($owner, $workspace, 'Roadmap');

        $category = app(CreateCategory::class)->execute($owner, $board, 'Backend', '#4f6f9f');
        app(UpdateCategory::class)->execute($owner, $category, 'Frontend', '#2563eb');
        Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->action === 'category.created');
        Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->action === 'category.updated');
        app(DeleteCategory::class)->execute($owner, $category);
        Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->action === 'category.deleted');

        $column = app(CreateBoardColumn::class)->execute($owner, $board, 'Review');
        app(UpdateBoardColumn::class)->execute($owner, $column, 'Done');
        Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->action === 'column.created');
        Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->action === 'column.updated');
        $orderedColumnIds = $board->columns()->pluck('id')->map(fn ($id): int => (int) $id)->reverse()->values()->all();
        app(ReorderBoardColumns::class)->execute($owner, $board, $orderedColumnIds);
        Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->action === 'columns.reordered');
        app(DeleteBoardColumn::class)->execute($owner, $column);
        Event::assertDispatched(BoardChanged::class, fn (BoardChanged $event): bool => $event->action === 'column.deleted');
    }

    public function test_invites_membership_and_activity_use_the_expected_channels(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();
        $member = User::factory()->create();
        Event::fake();

        $invitation = app(InviteWorkspaceMember::class)->execute($owner, $workspace, $member->email);
        Event::assertDispatched(UserRealtimeEvent::class, fn (UserRealtimeEvent $event): bool => $event->action === 'invitation.created' && $event->userId === $member->id);

        app(AcceptWorkspaceInvitation::class)->execute($member, $invitation->token);
        Event::assertDispatched(UserRealtimeEvent::class, fn (UserRealtimeEvent $event): bool => $event->action === 'workspace.available' && $event->userId === $member->id);
        Event::assertDispatched(WorkspaceChanged::class, fn (WorkspaceChanged $event): bool => $event->action === 'workspace.member_joined');

        app(RemoveWorkspaceMember::class)->execute($owner, $workspace, $member);
        Event::assertDispatched(UserRealtimeEvent::class, fn (UserRealtimeEvent $event): bool => $event->action === 'workspace.access_removed' && $event->userId === $member->id);

        $secondInvitation = app(InviteWorkspaceMember::class)->execute($owner, $workspace, $member->email);
        app(RejectWorkspaceInvitation::class)->execute($member, $secondInvitation);
        Event::assertDispatched(UserRealtimeEvent::class, fn (UserRealtimeEvent $event): bool => $event->action === 'invitation.rejected' && $event->userId === $owner->id);

        $memberAgain = User::factory()->create();
        $workspace->members()->attach($memberAgain->id, ['role' => 'member', 'joined_at' => now()]);
        app(LeaveWorkspace::class)->execute($memberAgain, $workspace);
        Event::assertDispatched(WorkspaceChanged::class, fn (WorkspaceChanged $event): bool => $event->action === 'workspace.member_left');
    }

    public function test_inviting_an_unknown_email_keeps_the_invitation_without_a_recipient_channel(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();
        Event::fake();

        app(InviteWorkspaceMember::class)->execute($owner, $workspace, 'new-user@example.com');

        Event::assertDispatched(UserRealtimeEvent::class, fn (UserRealtimeEvent $event): bool => $event->action === 'invitation.pending.created' && $event->userId === $owner->id);
        Event::assertNotDispatched(UserRealtimeEvent::class, fn (UserRealtimeEvent $event): bool => $event->action === 'invitation.created');
        $this->assertDatabaseHas('workspace_invitations', [
            'workspace_id' => $workspace->id,
            'email' => 'new-user@example.com',
        ]);
    }

    public function test_activity_log_event_contains_explicit_safe_payload_and_board_channel(): void
    {
        [$owner, $workspace] = $this->sharedWorkspace();
        $board = Board::create(['workspace_id' => $workspace->id, 'name' => 'Board']);
        Event::fake();

        app(LogActivity::class)->execute(
            $owner,
            $workspace,
            'board.updated',
            $board,
            $board,
            ['board_name' => 'Board'],
        );

        Event::assertDispatched(ActivityLogged::class, function (ActivityLogged $event) use ($owner, $workspace, $board): bool {
            return $event->workspaceId === $workspace->id
                && $event->boardId === $board->id
                && array_keys($event->broadcastWith()) === ['workspace_id', 'board_id', 'activity']
                && $event->broadcastOn()[0]->name === "private-workspace.{$workspace->id}"
                && $event->broadcastOn()[1]->name === "private-board.{$board->id}"
                && $event->broadcastWith()['activity']['actor']['name'] === $owner->name;
        });
    }

    /** @return array{0: User, 1: Workspace} */
    private function sharedWorkspace(): array
    {
        $owner = User::factory()->create();

        $owner->subscription()->update([
            'plan_id' => Plan::where('slug', 'team')->value('id'),
        ]);

        $workspace = Workspace::create([
            'owner_id' => $owner->id,
            'name' => 'Team',
            'type' => 'shared',
        ]);

        $workspace->members()->attach($owner->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return [$owner, $workspace];
    }

    /** @return array{socket_id: string, channel_name: string} */
    private function channelPayload(string $channel, int $id): array
    {
        return [
            'socket_id' => '1234.5678',
            'channel_name' => "private-{$channel}.{$id}",
        ];
    }
}
