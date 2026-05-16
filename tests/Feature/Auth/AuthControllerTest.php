<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');
    }

    public function test_register_creates_user_and_returns_token(): void
    {
        $payload = [
            'name' => 'Lucas Tattoo',
            'email' => 'lucas@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson(route('auth.register'), $payload);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'created_at', 'roles'],
                    'token',
                ],
                'message',
            ])
            ->assertJsonPath('data.user.email', 'lucas@example.com')
            ->assertJsonPath('data.user.roles', ['client']);

        $this->assertDatabaseHas('users', [
            'email' => 'lucas@example.com',
            'name' => 'Lucas Tattoo',
        ]);
    }

    public function test_register_assigns_client_role_to_new_user(): void
    {
        $response = $this->postJson(route('auth.register'), [
            'name' => 'Lucas Tattoo',
            'email' => 'lucas@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertOk();

        $user = User::where('email', 'lucas@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('client'));
    }

    public function test_register_fails_when_email_is_already_taken(): void
    {
        User::factory()->create(['email' => 'lucas@example.com']);

        $response = $this->postJson(route('auth.register'), [
            'name' => 'Lucas Tattoo',
            'email' => 'lucas@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Validation error',
            ])
            ->assertJsonValidationErrors('email');
    }

    public function test_register_fails_when_password_is_weak_or_unconfirmed(): void
    {
        $response = $this->postJson(route('auth.register'), [
            'name' => 'Lucas Tattoo',
            'email' => 'lucas@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_login_returns_token_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'lucas@example.com',
            'password' => 'password123',
        ]);
        $user->assignRole('client');

        $response = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'created_at', 'roles'],
                    'token',
                ],
                'message',
            ])
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.roles', ['client']);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'lucas@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson(route('auth.login'), [
            'email' => 'lucas@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_logout_revokes_authenticated_user_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('api-token');

        Sanctum::actingAs($user);

        $response = $this->postJson(route('auth.logout'));

        $response->assertOk()
            ->assertJson([
                'message' => 'Logged out successfully',
            ]);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_me_returns_authenticated_user_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        Sanctum::actingAs($user);

        $response = $this->getJson(route('auth.me'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'created_at', 'roles'],
            ])
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles', ['client']);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson(route('auth.me'));

        $response->assertStatus(401);
    }

    public function test_update_profile_updates_name_and_email_successfully(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson(route('auth.update-profile'), [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_update_profile_fails_when_email_is_already_taken(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->patchJson(route('auth.update-profile'), [
            'email' => 'taken@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_update_profile_fails_when_password_is_too_short(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->patchJson(route('auth.update-profile'), [
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_update_profile_updates_password_successfully(): void
    {
        $user = User::factory()->create(['password' => 'old-password123']);

        Sanctum::actingAs($user);

        $response = $this->patchJson(route('auth.update-profile'), [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertOk();

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('new-password123', $user->fresh()->password)
        );
    }
}
