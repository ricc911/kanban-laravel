<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_user_sees_only_non_expired_pending_invitations_for_their_email(): void
    {
        $user = User::factory()->create([
            'email' => 'target@example.com',
        ]);

        $owner = $this->teamUser();

        $pendingWorkspace = $this->sharedWorkspace($owner);
        $expiredWorkspace = $this->sharedWorkspace($owner);
        $acceptedWorkspace = $this->sharedWorkspace($owner);
        $otherWorkspace = $this->sharedWorkspace($owner);

        $mine = $this->invitation(
            $pendingWorkspace,
            $user->email
        );

        $this->invitation(
            $otherWorkspace,
            'other@example.com'
        );

        $this->invitation(
            $expiredWorkspace,
            $user->email,
            now()->subMinute()
        );

        $accepted = $this->invitation(
            $acceptedWorkspace,
            $user->email
        );

        $accepted->update([
            'accepted_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/invitations')
            ->assertOk();

        $this->assertSame(
            [$mine->id],
            collect($response->json('data'))
                ->pluck('id')
                ->all()
        );
    }

    public function test_recipient_can_accept_invitation_and_sees_workspace(): void
    {
        $owner = $this->teamUser();
        $user = User::factory()->create(['email' => 'target@example.com']);
        $invitation = $this->invitation($this->sharedWorkspace($owner), $user->email);

        $this->actingAs($user)->postJson("/api/invitations/{$invitation->token}/accept")->assertOk();
        $this->actingAs($user)->getJson('/api/invitations')->assertJsonCount(0, 'data');
        $this->assertTrue($user->fresh()->isMemberOf($invitation->workspace));
    }

    public function test_recipient_can_reject_invitation(): void
    {
        $owner = $this->teamUser();
        $user = User::factory()->create(['email' => 'target@example.com']);
        $invitation = $this->invitation($this->sharedWorkspace($owner), $user->email);

        $this->actingAs($user)->deleteJson("/api/invitations/{$invitation->id}")->assertNoContent();
        $this->assertDatabaseMissing('workspace_invitations', ['id' => $invitation->id]);
    }

    public function test_user_cannot_accept_or_reject_an_invitation_for_another_email(): void
    {
        $owner = $this->teamUser();
        $user = User::factory()->create(['email' => 'wrong@example.com']);
        $invitation = $this->invitation($this->sharedWorkspace($owner), 'target@example.com');

        $this->actingAs($user)->postJson("/api/invitations/{$invitation->token}/accept")->assertUnprocessable();
        $this->actingAs($user)->deleteJson("/api/invitations/{$invitation->id}")->assertUnprocessable();
    }

    public function test_guest_cannot_access_invitations(): void
    {
        $this->getJson('/api/invitations')->assertUnauthorized();
        $this->postJson('/api/invitations/'.Str::random(64).'/accept')->assertUnauthorized();
    }

    private function teamUser(): User
    {
        $user = User::factory()->create();
        $user->subscription()->update(['plan_id' => Plan::where('slug', 'team')->value('id')]);

        return $user;
    }

    private function sharedWorkspace(User $owner): Workspace
    {
        $workspace = Workspace::create(['owner_id' => $owner->id, 'name' => 'Team', 'type' => 'shared']);

        return $workspace;
    }

    private function invitation(Workspace $workspace, string $email, $expiresAt = null): WorkspaceInvitation
    {
        return WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => $email,
            'role' => 'member',
            'token' => Str::random(64),
            'expires_at' => $expiresAt ?? now()->addDay(),
        ]);
    }
}
