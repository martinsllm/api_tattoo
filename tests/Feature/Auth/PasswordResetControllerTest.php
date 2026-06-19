<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_sends_reset_notification_for_existing_user(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson(route('password.forgot'), [
            'email' => $user->email,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Se o e-mail estiver cadastrado, enviaremos as instruções de redefinição.');

        Notification::assertSentTo($user, PasswordResetNotification::class);
    }

    public function test_forgot_returns_success_for_unknown_email_without_sending(): void
    {
        Notification::fake();

        $response = $this->postJson(route('password.forgot'), [
            'email' => 'naoexiste@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Se o e-mail estiver cadastrado, enviaremos as instruções de redefinição.');

        Notification::assertNothingSent();
    }

    public function test_forgot_requires_a_valid_email(): void
    {
        $response = $this->postJson(route('password.forgot'), [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Validation error'])
            ->assertJsonValidationErrors('email');
    }

    public function test_reset_updates_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('senha-antiga'),
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson(route('password.reset'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nova-senha123',
            'password_confirmation' => 'nova-senha123',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Senha redefinida com sucesso.');

        $this->assertTrue(Hash::check('nova-senha123', $user->fresh()->password));
    }

    public function test_reset_revokes_existing_sanctum_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('api-token');

        $this->assertCount(1, $user->tokens);

        $token = Password::createToken($user);

        $this->postJson(route('password.reset'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nova-senha123',
            'password_confirmation' => 'nova-senha123',
        ])->assertOk();

        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('senha-antiga'),
        ]);

        $response = $this->postJson(route('password.reset'), [
            'token' => 'token-invalido',
            'email' => $user->email,
            'password' => 'nova-senha123',
            'password_confirmation' => 'nova-senha123',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Token inválido ou expirado.');

        $this->assertTrue(Hash::check('senha-antiga', $user->fresh()->password));
    }

    public function test_reset_fails_when_email_does_not_match_token(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $token = Password::createToken($user);

        $response = $this->postJson(route('password.reset'), [
            'token' => $token,
            'email' => $otherUser->email,
            'password' => 'nova-senha123',
            'password_confirmation' => 'nova-senha123',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Token inválido ou expirado.');
    }

    public function test_reset_requires_password_confirmation(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson(route('password.reset'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nova-senha123',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Validation error'])
            ->assertJsonValidationErrors('password');
    }

    public function test_reset_fails_with_short_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson(route('password.reset'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Validation error'])
            ->assertJsonValidationErrors('password');
    }

    public function test_reset_requires_token_and_email(): void
    {
        $response = $this->postJson(route('password.reset'), [
            'password' => 'nova-senha123',
            'password_confirmation' => 'nova-senha123',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Validation error'])
            ->assertJsonValidationErrors(['token', 'email']);
    }
}
