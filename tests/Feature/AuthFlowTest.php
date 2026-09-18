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
            'last_name' => 'Rossi',
            'username' => 'Mario_Rossi',
            'email' => 'mario@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/home');

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
            ->assertJsonPath('email', 'mario@example.com')
            ->assertJsonPath('username', 'mario_rossi');
    }

    public function test_registration_normalizes_username_and_rejects_case_insensitive_duplicate(): void
    {
        $this->postJson('/register', [
            'name' => 'Mario', 'last_name' => 'Rossi', 'username' => 'Mario_92',
            'email' => 'mario@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect('/home');

        $this->assertDatabaseHas('users', ['username' => 'mario_92']);
        $this->postJson('/logout');
        $this->postJson('/register', [
            'name' => 'Marco', 'last_name' => 'Bianchi', 'username' => 'MARIO_92',
            'email' => 'marco@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('username');
    }

    public function test_registration_rejects_invalid_usernames(): void
    {
        foreach (['ab', str_repeat('a', 31), 'has space', 'has@symbol', 'has.dot'] as $index => $username) {
            $this->postJson('/register', [
                'name' => 'User', 'last_name' => 'Test', 'username' => $username,
                'email' => "invalid-{$index}@example.com", 'password' => 'password123', 'password_confirmation' => 'password123',
            ])->assertUnprocessable()->assertJsonValidationErrors('username');
        }
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
