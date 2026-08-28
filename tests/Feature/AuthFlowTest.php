<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }

    public function test_user_can_register_and_receives_free_plan_and_personal_workspace(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();

        $user = User::where('email', 'mario@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);

        $this->assertSame(
            'free',
            $user->subscription()->with('plan')->firstOrFail()->plan->slug
        );

        /** @var Workspace $workspace */
        $workspace = $user->ownedWorkspaces()
            ->where('type', 'personal')
            ->firstOrFail();

        $this->assertSame('Personale', $workspace->name);

        $this->assertTrue(
            $workspace->members()
                ->where('users.id', $user->id)
                ->wherePivot('role', 'owner')
                ->exists()
        );

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'mario@example.com');
    }

    public function test_user_can_logout_and_login_again(): void
    {
        $user = User::create([
            'name' => 'Mario Rossi',
            'email' => 'mario@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->actingAs($user);

        $this->postJson('/logout')
            ->assertNoContent();

        $this->assertGuest();

        $this->getJson('/api/user')
            ->assertUnauthorized();

        $this->postJson('/login', [
            'email' => 'mario@example.com',
            'password' => 'password123',
        ])->assertOk();

        $this->assertAuthenticatedAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('email', 'mario@example.com');
    }
}
