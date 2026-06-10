<?php

namespace Tests\Feature\Auth;

use App\Models\ArtistProfile;
use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
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

        Notification::assertSentTo($user, EmailVerificationNotification::class);
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

    public function test_resend_invalidates_previous_verification_link(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $oldToken = $user->rotateEmailVerificationToken();
        $oldUrl = $this->signedVerifyUrl($user, $oldToken);

        Sanctum::actingAs($user);

        $this->postJson(route('email.resend-verification'))->assertOk();

        $user->refresh();

        $this->assertNotSame($oldToken, $user->email_verification_token);

        $this->getJson($oldUrl)
            ->assertForbidden()
            ->assertJsonPath('message', 'Link de verificação inválido.');

        $this->getJson($this->signedVerifyUrl($user))
            ->assertOk()
            ->assertJsonPath('message', 'E-mail verificado com sucesso.');
    }

    public function test_verify_marks_email_as_verified_with_valid_signed_url(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_token' => 'valid-token',
        ]);

        $response = $this->getJson($this->signedVerifyUrl($user, 'valid-token'));

        $response->assertOk()
            ->assertJsonPath('message', 'E-mail verificado com sucesso.');

        $user->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->email_verification_token);
    }

    public function test_verify_returns_success_when_email_already_verified(): void
    {
        $user = User::factory()->create([
            'email_verification_token' => 'valid-token',
        ]);

        $response = $this->getJson($this->signedVerifyUrl($user, 'valid-token'));

        $response->assertOk()
            ->assertJsonPath('message', 'E-mail já verificado.');
    }

    public function test_verify_returns_403_for_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_token' => 'valid-token',
        ]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1('wrong@email.com'),
                'token' => 'valid-token',
            ]
        );

        $response = $this->getJson($url);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Link de verificação inválido.');
    }

    public function test_verify_returns_403_for_invalid_token(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_token' => 'current-token',
        ]);

        $response = $this->getJson($this->signedVerifyUrl($user, 'stale-token'));

        $response->assertForbidden()
            ->assertJsonPath('message', 'Link de verificação inválido.');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verify_returns_403_for_expired_signed_url(): void
    {
        $user = User::factory()->unverified()->create([
            'email_verification_token' => 'valid-token',
        ]);

        $url = $this->signedVerifyUrl($user, 'valid-token', now()->subMinute());

        $response = $this->getJson($url);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Link de verificação inválido ou expirado.');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verify_change_applies_pending_email_with_valid_signed_url(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'pending_email_token' => 'valid-token',
        ]);

        $response = $this->getJson($this->signedVerifyChangeUrl($user, 'valid-token'));

        $response->assertOk()
            ->assertJsonPath('message', 'E-mail alterado com sucesso.');

        $user->refresh();

        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNull($user->pending_email_token);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_verify_change_reactivates_artist_profile(): void
    {
        Role::findOrCreate('artist');

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'pending_email_token' => 'valid-token',
            'artist_catalog_suppressed_for_pending_email' => true,
        ]);
        $user->assignRole('artist');
        $artist = ArtistProfile::factory()->for($user)->inactive()->create();

        $this->getJson($this->signedVerifyChangeUrl($user, 'valid-token'))->assertOk();

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'artist_catalog_suppressed_for_pending_email' => false,
        ]);
    }

    public function test_verify_change_keeps_inactive_artist_when_not_suppressed_by_pending_email(): void
    {
        Role::findOrCreate('artist');

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'pending_email_token' => 'valid-token',
            'artist_catalog_suppressed_for_pending_email' => false,
        ]);
        $user->assignRole('artist');
        $artist = ArtistProfile::factory()->for($user)->inactive()->create();

        $this->getJson($this->signedVerifyChangeUrl($user, 'valid-token'))->assertOk();

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => false,
        ]);

        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_verify_change_returns_artist_to_catalog(): void
    {
        Role::findOrCreate('artist');

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'pending_email_token' => 'valid-token',
            'artist_catalog_suppressed_for_pending_email' => true,
        ]);
        $user->assignRole('artist');
        $artist = ArtistProfile::factory()->for($user)->inactive()->create([
            'studio_name' => 'Volta Catálogo',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson(route('artist.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson($this->signedVerifyChangeUrl($user, 'valid-token'))->assertOk();

        $this->getJson(route('artist.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $artist->id);

        $this->getJson(route('artist.show', $artist->id))
            ->assertOk()
            ->assertJsonPath('data.studio_name', 'Volta Catálogo');
    }

    public function test_verify_change_returns_422_when_no_pending_email(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'pending_email' => null,
            'pending_email_token' => null,
        ]);

        $url = URL::temporarySignedRoute(
            'verification.verify-change',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1('ghost@example.com'),
                'token' => 'ghost-token',
            ]
        );

        $response = $this->getJson($url);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Nenhuma troca de e-mail pendente.');

        $this->assertSame('user@example.com', $user->fresh()->email);
    }

    public function test_verify_change_returns_403_for_invalid_hash(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'pending_email_token' => 'valid-token',
        ]);

        $url = URL::temporarySignedRoute(
            'verification.verify-change',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1('wrong@example.com'),
                'token' => 'valid-token',
            ]
        );

        $response = $this->getJson($url);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Link de verificação inválido.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
        ]);
    }

    public function test_verify_change_returns_403_for_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'pending_email_token' => 'current-token',
        ]);

        $response = $this->getJson($this->signedVerifyChangeUrl($user, 'stale-token'));

        $response->assertForbidden()
            ->assertJsonPath('message', 'Link de verificação inválido.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
        ]);
    }

    public function test_verify_change_returns_403_for_expired_signed_url(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'pending_email_token' => 'valid-token',
        ]);

        $url = $this->signedVerifyChangeUrl($user, 'valid-token', now()->subMinute());

        $response = $this->getJson($url);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Link de verificação inválido ou expirado.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
        ]);
    }

    private function signedVerifyUrl(User $user, ?string $token = null, ?\DateTimeInterface $expiresAt = null): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            $expiresAt ?? now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
                'token' => $token ?? (string) $user->email_verification_token,
            ]
        );
    }

    private function signedVerifyChangeUrl(User $user, ?string $token = null, ?\DateTimeInterface $expiresAt = null): string
    {
        return URL::temporarySignedRoute(
            'verification.verify-change',
            $expiresAt ?? now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->pending_email),
                'token' => $token ?? (string) $user->pending_email_token,
            ]
        );
    }
}
