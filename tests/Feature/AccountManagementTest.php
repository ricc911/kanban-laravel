<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_name(): void
    {
        $user = User::factory()->create(['name' => 'Old']);

        $this->actingAs($user)->patchJson('/api/account/profile', [
            'name' => 'New',
            'last_name' => 'Surname',
            'username' => 'New_User',
        ])->assertOk()->assertJsonPath('data.name', 'New')->assertJsonPath('data.username', 'new_user');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New', 'last_name' => 'Surname', 'username' => 'new_user', 'email' => $user->email]);
    }

    public function test_guest_cannot_access_account_endpoints(): void
    {
        $this->patchJson('/api/account/profile', ['name' => 'New'])->assertUnauthorized();
        $this->patchJson('/api/account/password', [])->assertUnauthorized();
    }

    public function test_profile_rejects_another_users_username_but_accepts_own_username_case_insensitively(): void
    {
        $user = User::factory()->create(['username' => 'riccardo']);
        User::factory()->create(['username' => 'marco']);

        $this->actingAs($user)->patchJson('/api/account/profile', [
            'name' => $user->name,
            'last_name' => $user->last_name,
            'username' => 'RICCARDO',
        ])->assertOk()->assertJsonPath('data.username', 'riccardo');

        $this->actingAs($user)->patchJson('/api/account/profile', [
            'name' => $user->name,
            'last_name' => $user->last_name,
            'username' => 'MARCO',
        ])->assertUnprocessable()->assertJsonValidationErrors('username');
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->actingAs($user)->patchJson('/api/account/password', [
            'current_password' => 'wrong',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
    }

    public function test_user_can_change_password_and_use_it_to_login(): void
    {
        $user = User::factory()->create(['email' => 'account@example.com', 'password' => 'secret123']);

        $this->actingAs($user)->patchJson('/api/account/password', [
            'current_password' => 'secret123',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ])->assertOk();
        $this->assertTrue(Hash::check('newsecret123', $user->fresh()->password));
        $this->postJson('/logout');
        $this->postJson('/login', ['email' => $user->email, 'password' => 'newsecret123'])->assertOk();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
    }
}
