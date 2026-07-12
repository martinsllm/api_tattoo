<?php

namespace Tests\Feature\Auth;

use App\Models\ArtistProfile;
use App\Models\Review;
use App\Models\User;
use App\Notifications\PendingEmailChangeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
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
            'accepted_terms' => true,
        ];

        $response = $this->postJson(route('auth.register'), $payload);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'created_at', 'roles'],
                    'token',
                    'expires_at',
                ],
                'message',
            ])
            ->assertJsonPath('data.user.email', 'lucas@example.com')
            ->assertJsonPath('data.user.roles', ['client']);

        $this->assertNotNull($response->json('data.expires_at'));

        $this->assertDatabaseHas('users', [
            'email' => 'lucas@example.com',
            'name' => 'Lucas Tattoo',
        ]);

        $user = User::where('email', 'lucas@example.com')->firstOrFail();
        $this->assertNotNull($user->accepted_terms_at);
    }

    public function test_register_assigns_client_role_to_new_user(): void
    {
        $response = $this->postJson(route('auth.register'), [
            'name' => 'Lucas Tattoo',
            'email' => 'lucas@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accepted_terms' => true,
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
            'accepted_terms' => true,
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
            'accepted_terms' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_register_fails_when_terms_are_not_accepted(): void
    {
        $response = $this->postJson(route('auth.register'), [
            'name' => 'Lucas Tattoo',
            'email' => 'lucas@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('accepted_terms');

        $this->assertDatabaseMissing('users', [
            'email' => 'lucas@example.com',
        ]);
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
                    'expires_at',
                ],
                'message',
            ])
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.roles', ['client']);

        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_expired_token_returns_unauthenticated(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token', ['*'], now()->subMinute());

        $response = $this->getJson(route('auth.me'), [
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);
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

    public function test_login_is_throttled_per_account_after_five_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'lucas@example.com',
            'password' => 'password123',
        ]);

        $credentials = [
            'email' => 'lucas@example.com',
            'password' => 'wrong-password',
        ];

        // As 5 primeiras tentativas passam pelo limiter `login` (5/min por email|ip) e falham com 401.
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(route('auth.login'), $credentials)
                ->assertStatus(401);
        }

        // A 6ª estoura o balde e é barrada antes de chegar no controller, com mensagem genérica.
        $this->postJson(route('auth.login'), $credentials)
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many requests');
    }

    public function test_login_throttle_is_isolated_per_account(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password' => 'password123',
        ]);

        $other = User::factory()->create([
            'email' => 'other@example.com',
            'password' => 'password123',
        ]);
        $other->assignRole('client');

        // Esgota o balde `email|ip` da primeira conta.
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(route('auth.login'), [
                'email' => 'victim@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->postJson(route('auth.login'), [
            'email' => 'victim@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);

        // Outra conta usa um balde distinto e loga normalmente, sem ser afetada.
        $this->postJson(route('auth.login'), [
            'email' => 'other@example.com',
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_login_throttle_blocks_same_account_across_multiple_ips(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password' => 'password123',
        ]);

        $credentials = [
            'email' => 'victim@example.com',
            'password' => 'wrong-password',
        ];

        // 20 tentativas espalhadas em 4 IPs (5 cada). Nenhuma estoura o balde `email|ip` (5/min),
        // mas somadas enchem o balde por-conta (20/min), que ignora o IP.
        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4'] as $ip) {
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $this->withServerVariables(['REMOTE_ADDR' => $ip])
                    ->postJson(route('auth.login'), $credentials)
                    ->assertStatus(401);
            }
        }

        // Um IP totalmente novo: o balde `email|ip` está zerado, mas o balde por-conta já estourou.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->postJson(route('auth.login'), $credentials)
            ->assertStatus(429);
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

    public function test_export_returns_authenticated_user_personal_data(): void
    {
        Role::findOrCreate('artist');

        $user = User::factory()->create();
        $user->assignRole('artist');
        $artistProfile = ArtistProfile::factory()->for($user)->create([
            'studio_name' => 'Ink House',
        ]);
        $reviewedArtist = ArtistProfile::factory()->create();
        $favoriteArtist = ArtistProfile::factory()->create();
        $review = Review::factory()->create([
            'user_id' => $user->id,
            'artist_profile_id' => $reviewedArtist->id,
            'rating' => 5,
            'comment' => 'Excelente trabalho.',
        ]);
        $user->favorites()->attach($favoriteArtist->id);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('auth.export'));

        $response->assertOk()
            ->assertJsonPath('message', 'Dados exportados com sucesso.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.artist_profile.id', $artistProfile->id)
            ->assertJsonPath('data.artist_profile.studio_name', 'Ink House')
            ->assertJsonPath('data.reviews.0.id', $review->id)
            ->assertJsonPath('data.reviews.0.artist_profile_id', $reviewedArtist->id)
            ->assertJsonPath('data.reviews.0.rating', 5)
            ->assertJsonPath('data.reviews.0.comment', 'Excelente trabalho.')
            ->assertJsonPath('data.favorites.0.artist_profile_id', $favoriteArtist->id)
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'artist_profile',
                    'reviews' => [
                        '*' => ['id', 'artist_profile_id', 'rating', 'comment', 'created_at'],
                    ],
                    'favorites' => [
                        '*' => ['artist_profile_id', 'favorited_at'],
                    ],
                ],
            ]);
    }

    public function test_export_does_not_include_other_users_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownReviewedArtist = ArtistProfile::factory()->create();
        $otherReviewedArtist = ArtistProfile::factory()->create();
        $ownFavorite = ArtistProfile::factory()->create();
        $otherFavorite = ArtistProfile::factory()->create();
        $ownReview = Review::factory()->create([
            'user_id' => $user->id,
            'artist_profile_id' => $ownReviewedArtist->id,
        ]);
        $otherReview = Review::factory()->create([
            'user_id' => $otherUser->id,
            'artist_profile_id' => $otherReviewedArtist->id,
        ]);
        $user->favorites()->attach($ownFavorite->id);
        $otherUser->favorites()->attach($otherFavorite->id);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('auth.export'));

        $response->assertOk()
            ->assertJsonCount(1, 'data.reviews')
            ->assertJsonCount(1, 'data.favorites')
            ->assertJsonPath('data.reviews.0.id', $ownReview->id)
            ->assertJsonPath('data.favorites.0.artist_profile_id', $ownFavorite->id);

        $this->assertNotSame($otherReview->id, $response->json('data.reviews.0.id'));
        $this->assertNotSame($otherFavorite->id, $response->json('data.favorites.0.artist_profile_id'));
    }

    public function test_export_requires_authentication(): void
    {
        $response = $this->getJson(route('auth.export'));

        $response->assertStatus(401);
    }

    public function test_export_is_rate_limited(): void
    {
        $user = User::factory()->create([
            'id' => random_int(10_000, 99_999),
        ]);
        $ipAddress = '203.0.113.'.random_int(1, 254);

        Sanctum::actingAs($user);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => $ipAddress])
                ->getJson(route('auth.export'))
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => $ipAddress])
            ->getJson(route('auth.export'))
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too many requests');
    }

    public function test_update_profile_sends_pending_email_change_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);
        Sanctum::actingAs($user);

        $this->patchJson(route('auth.update-profile'), [
            'email' => 'new@example.com',
        ])->assertOk();

        Notification::assertSentTo(
            $user,
            PendingEmailChangeNotification::class,
            function (PendingEmailChangeNotification $notification, array $channels, object $notifiable) use ($user): bool {
                return $notifiable->is($user)
                    && $notifiable->pending_email === 'new@example.com';
            }
        );
    }

    public function test_update_profile_stores_pending_email_and_updates_name_in_same_request(): void
    {
        Notification::fake();

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
            ->assertJsonPath('message', 'Email de verificação enviado.')
            ->assertJsonPath('data', null);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
        ]);
    }

    public function test_update_profile_stores_pending_email_and_updates_password_in_same_request(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => 'old-password123',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson(route('auth.update-profile'), [
            'email' => 'new@example.com',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Email de verificação enviado.')
            ->assertJsonPath('data', null);

        $user->refresh();

        $this->assertSame('new@example.com', $user->pending_email);
        $this->assertSame('old@example.com', $user->email);
        $this->assertTrue(Hash::check('new-password123', $user->password));
    }

    public function test_update_profile_stores_pending_email_without_artist_profile(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);
        $user->assignRole('client');

        Sanctum::actingAs($user);

        $response = $this->patchJson(route('auth.update-profile'), [
            'email' => 'new@example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Email de verificação enviado.')
            ->assertJsonPath('data', null);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'artist_catalog_suppressed_for_pending_email' => false,
        ]);

        $this->assertDatabaseMissing('artist_profiles', [
            'user_id' => $user->id,
        ]);
    }

    public function test_update_profile_invalidates_previous_pending_email_change_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);
        Sanctum::actingAs($user);

        $this->patchJson(route('auth.update-profile'), [
            'email' => 'new@example.com',
        ])->assertOk();

        $user->refresh();
        $oldToken = $user->pending_email_token;

        $oldUrl = URL::temporarySignedRoute(
            'verification.verify-change',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->pending_email),
                'token' => $oldToken,
            ]
        );

        $this->patchJson(route('auth.update-profile'), [
            'email' => 'new@example.com',
        ])->assertOk();

        $user->refresh();

        $this->assertNotSame($oldToken, $user->pending_email_token);

        $this->getJson($oldUrl)
            ->assertForbidden()
            ->assertJsonPath('message', 'Link de verificação inválido.');

        $this->getJson(URL::temporarySignedRoute(
            'verification.verify-change',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->pending_email),
                'token' => $user->pending_email_token,
            ]
        ))->assertOk()
            ->assertJsonPath('message', 'E-mail alterado com sucesso.');
    }

    public function test_update_profile_deactivates_artist_profile_when_requesting_email_change(): void
    {
        Notification::fake();

        Role::findOrCreate('artist');

        $user = User::factory()->create(['email' => 'old@example.com']);
        $user->assignRole('artist');
        $artist = ArtistProfile::factory()->for($user)->create(['is_active' => true]);

        Sanctum::actingAs($user);

        $this->patchJson(route('auth.update-profile'), [
            'email' => 'new@example.com',
        ])->assertOk();

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'artist_catalog_suppressed_for_pending_email' => true,
        ]);
    }

    public function test_update_profile_does_not_suppress_already_inactive_artist_when_requesting_email_change(): void
    {
        Notification::fake();

        Role::findOrCreate('artist');

        $user = User::factory()->create(['email' => 'old@example.com']);
        $user->assignRole('artist');
        $artist = ArtistProfile::factory()->for($user)->inactive()->create();

        Sanctum::actingAs($user);

        $this->patchJson(route('auth.update-profile'), [
            'email' => 'new@example.com',
        ])->assertOk();

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'pending_email' => 'new@example.com',
            'artist_catalog_suppressed_for_pending_email' => false,
        ]);
    }

    public function test_update_profile_hides_artist_from_catalog_while_email_change_is_pending(): void
    {
        Notification::fake();

        Role::findOrCreate('artist');

        $user = User::factory()->create(['email' => 'old@example.com']);
        $user->assignRole('artist');
        $hiddenArtist = ArtistProfile::factory()->for($user)->create([
            'is_active' => true,
            'studio_name' => 'Estúdio Pendente',
        ]);
        ArtistProfile::factory()->create(['studio_name' => 'Outro Ativo']);

        Sanctum::actingAs($user);

        $this->patchJson(route('auth.update-profile'), [
            'email' => 'new@example.com',
        ])->assertOk();

        $indexResponse = $this->getJson(route('artist.index'));

        $indexResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.studio_name', 'Outro Ativo');

        $this->getJson(route('artist.show', $hiddenArtist->id))
            ->assertNotFound();
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
            Hash::check('new-password123', $user->fresh()->password)
        );
    }

    public function test_update_profile_revokes_tokens_when_requesting_email_change(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'old@example.com']);
        $user->createToken('api-token');
        $user->createToken('api-token');

        Sanctum::actingAs($user);

        $this->patchJson(route('auth.update-profile'), [
            'email' => 'new@example.com',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_with_old_email_still_works_while_email_change_is_pending(): void
    {
        User::factory()->create([
            'email' => 'old@example.com',
            'password' => 'password123',
            'pending_email' => 'new@example.com',
        ]);

        $response = $this->postJson(route('auth.login'), [
            'email' => 'old@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'old@example.com');
    }

    public function test_cancel_pending_email_clears_pending_email(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'pending_email_token' => 'pending-token',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('auth.cancel-pending-email'));

        $response->assertOk()
            ->assertJsonPath('message', 'Troca de e-mail cancelada.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'old@example.com',
            'pending_email' => null,
            'pending_email_token' => null,
        ]);
    }

    public function test_cancel_pending_email_reactivates_artist_profile(): void
    {
        Role::findOrCreate('artist');

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'artist_catalog_suppressed_for_pending_email' => true,
        ]);
        $user->assignRole('artist');
        $artist = ArtistProfile::factory()->for($user)->create(['is_active' => false]);

        Sanctum::actingAs($user);

        $this->deleteJson(route('auth.cancel-pending-email'))->assertOk();

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'artist_catalog_suppressed_for_pending_email' => false,
        ]);
    }

    public function test_cancel_pending_email_keeps_inactive_artist_when_not_suppressed_by_pending_email(): void
    {
        Role::findOrCreate('artist');

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'artist_catalog_suppressed_for_pending_email' => false,
        ]);
        $user->assignRole('artist');
        $artist = ArtistProfile::factory()->for($user)->inactive()->create();

        Sanctum::actingAs($user);

        $this->deleteJson(route('auth.cancel-pending-email'))->assertOk();

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => false,
        ]);
    }

    public function test_cancel_pending_email_returns_artist_to_catalog(): void
    {
        Role::findOrCreate('artist');

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
            'artist_catalog_suppressed_for_pending_email' => true,
        ]);
        $user->assignRole('artist');
        $hiddenArtist = ArtistProfile::factory()->for($user)->create([
            'is_active' => false,
            'studio_name' => 'Estúdio Cancelado',
        ]);
        ArtistProfile::factory()->create(['studio_name' => 'Outro Ativo']);

        Sanctum::actingAs($user);

        $this->deleteJson(route('auth.cancel-pending-email'))->assertOk();

        $indexResponse = $this->getJson(route('artist.index'));

        $indexResponse->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['studio_name' => 'Estúdio Cancelado']);
    }

    public function test_cancel_pending_email_returns_422_when_no_pending_email(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('auth.cancel-pending-email'));

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Nenhuma troca de e-mail pendente.');
    }

    public function test_cancel_pending_email_requires_authentication(): void
    {
        $response = $this->deleteJson(route('auth.cancel-pending-email'));

        $response->assertStatus(401);
    }
}
