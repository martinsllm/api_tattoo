<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_resend_sends_verification_notification_for_unverified_user(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson(route('email.resend-verification'));

        $response->assertOk()
            ->assertJsonPath('message', 'Link de verificação enviado.');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_returns_422_when_email_already_verified(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson(route('email.resend-verification'));

        $response->assertStatus(422)
            ->assertJsonPath('message', 'E-mail já verificado.');

        Notification::assertNothingSent();
    }

    public function test_resend_requires_authentication(): void
    {
        $response = $this->postJson(route('email.resend-verification'));

        $response->assertUnauthorized();
    }

    public function test_verify_marks_email_as_verified_with_valid_signed_url(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $response = $this->getJson($url);

        $response->assertOk()
            ->assertJsonPath('message', 'E-mail verificado com sucesso.');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verify_returns_success_when_email_already_verified(): void
    {
        $user = User::factory()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $response = $this->getJson($url);

        $response->assertOk()
            ->assertJsonPath('message', 'E-mail já verificado.');
    }

    public function test_verify_returns_403_for_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1('wrong@email.com'),
            ]
        );

        $response = $this->getJson($url);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Link de verificação inválido.');
    }

    public function test_verify_returns_403_for_expired_signed_url(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $response = $this->getJson($url);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Link de verificação inválido ou expirado.');

        $this->assertNull($user->fresh()->email_verified_at);
    }
}
