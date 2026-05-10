<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

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
                    'user' => ['id', 'name', 'email', 'created_at'],
                    'token',
                ],
                'message',
            ])
            ->assertJsonPath('data.user.email', 'lucas@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'lucas@example.com',
            'name' => 'Lucas Tattoo',
        ]);
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

        $response = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'created_at'],
                    'token',
                ],
                'message',
            ])
            ->assertJsonPath('data.user.id', $user->id);
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
}
