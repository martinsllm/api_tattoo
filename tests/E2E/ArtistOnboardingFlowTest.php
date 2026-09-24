<?php

namespace Tests\E2E;

use App\Jobs\GenerateArtistImageThumbnail;
use App\Models\ArtistImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArtistOnboardingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');
        Role::findOrCreate('artist');
    }

    public function test_user_can_register_verify_email_create_profile_and_upload_images(): void
    {
        $email = 'test@example.com';

        $payload = [
            'name' => 'Test Artist',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'accepted_terms' => true,
        ];

        $registerResponse = $this->postJson(route('auth.register'), $payload);

        $token = $registerResponse->json('data.token');
        $userId = $registerResponse->json('data.user.id');

        $registerResponse->assertOk()
            ->assertJsonPath('data.user.email', $email)
            ->assertJsonPath('data.user.roles', ['client']);

        $this->withToken($token);

        Queue::fake();

        $resendResponse = $this->postJson(route('email.resend-verification'));

        $resendResponse->assertOk()
            ->assertJsonPath('message', 'Link de verificação enviado.');

        $user = User::find($userId);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
                'token' => $user->email_verification_token,
            ]
        );

        $verifyResponse = $this->getJson($url);

        $verifyResponse->assertOk()
            ->assertJsonPath('message', 'E-mail verificado com sucesso.');

        $user->refresh();

        $this->assertTrue($user->hasVerifiedEmail());

        Sanctum::actingAs($user);

        $createProfileResponse = $this->postJson(route('artist.store'), [
            'studio_name' => 'Test Studio',
            'city' => 'Balneário Camboriú',
            'state' => 'SC',
            'latitude' => -27.216667,
            'longitude' => -48.616667,
        ]);

        $artistId = $createProfileResponse->json('data.id');

        $createProfileResponse->assertStatus(201)
            ->assertJsonPath('data.studio_name', 'Test Studio');

        $this->assertTrue($user->hasRole('artist'));

        Storage::fake('public');

        $uploadResponse = $this->postJson(route('artist.image.store', $artistId), [
            'images' => [
                UploadedFile::fake()->image('image1.jpg'),
                UploadedFile::fake()->image('image2.jpg'),
            ],
        ]);

        $uploadResponse->assertStatus(201)
            ->assertJsonPath('message', 'Images uploaded successfully')
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseCount('artist_images', 2);

        foreach (ArtistImage::all() as $image) {
            $this->assertSame($artistId, $image->artist_profile_id);
            Storage::disk('public')->assertExists($image->image_url);

            Queue::assertPushed(GenerateArtistImageThumbnail::class, function (GenerateArtistImageThumbnail $job) use ($image): bool {
                return $job->artistImageId === $image->id;
            });
        }
    }
}
