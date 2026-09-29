<?php

namespace Tests\E2E;

use App\Models\ArtistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientReviewReplyFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');
        Role::findOrCreate('artist');
    }

    public function test_client_can_review_and_artist_can_reply()
    {
        $client = User::factory()->create([
            'email' => 'client@example.com',
            'password' => 'password',
        ]);

        $profileOwner = User::factory()->create([
            'email' => 'artist@example.com',
            'password' => 'password',
        ]);

        $client->assignRole('client');
        $profileOwner->assignRole('artist');

        $artistProfile = ArtistProfile::factory()->create([
            'user_id' => $profileOwner->id,
        ]);

        $this->assertDatabaseHas('artist_profiles', [
            'user_id' => $profileOwner->id,
        ]);

        $loginResponse = $this->postJson(route('auth.login'), [
            'email' => $client->email,
            'password' => 'password',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('data.user.roles', ['client']);

        $tokenClient = $loginResponse->json('data.token');

        $this->assertNotEmpty($tokenClient);

        $this->withToken($tokenClient);

        $reviewResponse = $this->postJson(route('review.store'), [
            'artist_profile_id' => $artistProfile->id,
            'rating' => 5,
            'comment' => 'Great artist!',
        ]);

        $reviewId = $reviewResponse->json('data.id');

        $reviewResponse->assertCreated()
            ->assertJsonPath('message', 'Review created successfully');

        $this->assertDatabaseHas('reviews', [
            'user_id' => $client->id,
            'artist_profile_id' => $artistProfile->id,
            'rating' => 5,
            'comment' => 'Great artist!',
        ]);

        Auth::forgetGuards();
        $this->withoutToken();
        Auth::shouldUse('web');

        $loginResponse = $this->postJson(route('auth.login'), [
            'email' => $profileOwner->email,
            'password' => 'password',
        ]);

        $tokenArtist = $loginResponse->json('data.token');

        $loginResponse->assertOk()
            ->assertJsonPath('data.user.roles', ['artist']);

        $this->assertNotEmpty($tokenArtist);

        $this->withToken($tokenArtist);

        $replyResponse = $this->patchJson(route('review.reply', $reviewId), [
            'reply' => 'Thank you!',
        ]);

        $this->assertDatabaseHas('reviews', [
            'id' => $reviewId,
            'reply' => 'Thank you!',
        ]);

        $replyResponse->assertOk()
            ->assertJsonPath('message', 'Review replied successfully');

    }
}
