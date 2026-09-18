<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\Plans\PlanLimitService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SharedWorkspaceRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_new_free_user_has_one_owner_only_personal_workspace_and_no_shared_quota(): void
    {
        $owner = User::factory()->create();
        $personal = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();

        $this->assertSame('free', $owner->subscription->plan->slug);
        $this->assertSame($owner->id, $personal->owner_id);
        $this->assertSame([$owner->id], $personal->members()->pluck('users.id')->all());
        $this->assertSame(0, app(PlanLimitService::class)->ownedSharedWorkspaceCount($owner));
        $this->assertFalse(app(PlanLimitService::class)->canCreateSharedWorkspace($owner));
        $this->actingAs($owner)->getJson('/api/workspaces')
            ->assertOk()
            ->assertJsonPath('meta.max_shared_workspaces', 0);
    }

    public function test_personal_workspace_rejects_invitation_creation_and_listing(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $personal = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();

        $this->actingAs($owner)
            ->postJson("/api/workspaces/{$personal->id}/invitations", ['email' => $other->email])
            ->assertUnprocessable()
            ->assertJsonPath('errors.workspace.0', Workspace::PERSONAL_SHARING_MESSAGE);
        $this->actingAs($owner)
            ->getJson("/api/workspaces/{$personal->id}/invitations")
            ->assertUnprocessable()
            ->assertJsonPath('errors.workspace.0', Workspace::PERSONAL_SHARING_MESSAGE);

        $this->assertSame(0, $personal->invitations()->count());
        $this->assertSame(1, $personal->members()->count());
    }

    public function test_forged_personal_invitation_cannot_add_a_member(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $personal = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();
        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $personal->id,
            'email' => $other->email,
            'role' => 'member',
            'token' => Str::random(64),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($other)
            ->postJson("/api/invitations/{$invitation->token}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('errors.workspace.0', Workspace::PERSONAL_SHARING_MESSAGE);
        $this->actingAs($other)->getJson('/api/invitations')->assertOk()->assertJsonCount(0, 'data');

        $this->assertSame(1, $personal->members()->count());
        $this->assertNull($invitation->fresh()->accepted_at);
    }

    public function test_manual_membership_insertion_into_personal_workspace_is_rejected_from_both_relations(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $personal = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();

        foreach ([
            fn () => $personal->members()->attach($other->id, ['role' => 'member', 'joined_at' => now()]),
            fn () => $other->workspaces()->attach($personal->id, ['role' => 'member', 'joined_at' => now()]),
            fn () => $personal->members()->syncWithoutDetaching([$other->id => ['role' => 'member', 'joined_at' => now()]]),
        ] as $attach) {
            try {
                $attach();
                $this->fail('La membership personale deve essere rifiutata.');
            } catch (ValidationException $exception) {
                $this->assertSame(
                    Workspace::PERSONAL_SHARING_MESSAGE,
                    $exception->errors()['workspace'][0]
                );
            }
        }

        $this->assertSame([$owner->id], $personal->members()->pluck('users.id')->all());
    }

    public function test_personal_workspace_rejects_member_role_and_remove_endpoints(): void
    {
        $owner = User::factory()->create();
        $personal = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();

        $this->actingAs($owner)
            ->patchJson("/api/workspaces/{$personal->id}/members/{$owner->id}/role", ['role' => 'viewer'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.workspace.0', Workspace::PERSONAL_SHARING_MESSAGE);
        $this->actingAs($owner)
            ->deleteJson("/api/workspaces/{$personal->id}/members/{$owner->id}")
            ->assertUnprocessable()
            ->assertJsonPath('errors.workspace.0', Workspace::PERSONAL_SHARING_MESSAGE);

        $this->assertSame('owner', $personal->members()->firstOrFail()->pivot->role);
    }

    public function test_api_payload_cannot_change_a_personal_workspace_type(): void
    {
        $owner = User::factory()->create();
        $personal = $owner->ownedWorkspaces()->where('type', 'personal')->firstOrFail();

        $this->actingAs($owner)
            ->patchJson("/api/workspaces/{$personal->id}", ['name' => 'Privato', 'type' => 'shared'])
            ->assertOk()
            ->assertJsonPath('data.type', 'personal');
        $this->actingAs($owner)
            ->postJson('/api/workspaces', ['name' => 'Another personal', 'type' => 'personal'])
            ->assertUnprocessable();

        $this->assertSame('personal', $personal->fresh()->type);
        $this->assertSame(1, $owner->ownedWorkspaces()->count());
    }

    public function test_workspace_index_exposes_shared_limit_without_counting_personal_workspace(): void
    {
        $owner = User::factory()->create();
        $owner->subscription()->update(['plan_id' => Plan::where('slug', 'team')->value('id')]);
        $response = $this->actingAs($owner)->getJson('/api/workspaces')
            ->assertOk()
            ->assertJsonPath('meta.max_shared_workspaces', 3)
            ->assertJsonCount(1, 'data');

        $this->assertSame('personal', $response->json('data.0.type'));
        $this->assertSame(0, app(PlanLimitService::class)->ownedSharedWorkspaceCount($owner));
    }
}
