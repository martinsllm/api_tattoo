<?php

namespace Tests\Feature\Review;

use App\Models\ArtistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
    }

    public function test_store_creates_review_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $artist = ArtistProfile::factory()->create();

        Sanctum::actingAs($user);

        $payload = [
            'artist_profile_id' => $artist->id,
            'rating' => 5,
            'comment' => 'Great artist!',
        ];

        $response = $this->postJson(route('review.store'), $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'rating',
                    'comment',
                    'created_at',
                    'user' => [
                        'id',
                        'name',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'artist_profile_id' => $artist->id,
            'rating' => 5,
            'comment' => 'Great artist!',
        ]);
    }

    public function test_store_requires_authentication(): void
    {
        $artist = ArtistProfile::factory()->create();

        $response = $this->postJson(route('review.store'), [
            'artist_profile_id' => $artist->id,
            'rating' => 5,
            'comment' => 'Great artist!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated',
            ]);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_store_forbids_admin_from_creating_review(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $artist = ArtistProfile::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson(route('review.store'), [
            'artist_profile_id' => $artist->id,
            'rating' => 5,
            'comment' => 'Great artist!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden',
            ]);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_store_rejects_authenticated_user_from_reviewing_their_own_artist(): void
    {
        $user = User::factory()->create();

        $artist = ArtistProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $payload = [
            'artist_profile_id' => $artist->id,
            'rating' => 5,
            'comment' => 'Great artist!',
        ];

        $response = $this->postJson(route('review.store'), $payload);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'You cannot review yourself.',
            ]);

        $this->assertDatabaseMissing('reviews', [
            'user_id' => $user->id,
            'artist_profile_id' => $artist->id,
        ]);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_store_rejects_authenticated_user_from_reviewing_the_same_artist_twice(): void
    {
        $user = User::factory()->create();

        $artist = ArtistProfile::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson(route('review.store'), [
            'artist_profile_id' => $artist->id,
            'rating' => 5,
            'comment' => 'Great artist!',
        ])->assertStatus(201);

        $response = $this->postJson(route('review.store'), [
            'artist_profile_id' => $artist->id,
            'rating' => 5,
            'comment' => 'Great artist!',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'You have already reviewed this artist.',
            ]);

        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_rejects_authenticated_user_from_reviewing_an_inactive_artist(): void
    {
        $user = User::factory()->create();

        $artist = ArtistProfile::factory()->inactive()->create();

        Sanctum::actingAs($user);

        $payload = [
            'artist_profile_id' => $artist->id,
            'rating' => 5,
            'comment' => 'Great artist!',
        ];

        $response = $this->postJson(route('review.store'), $payload);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Resource not found',
            ]);

        $this->assertDatabaseCount('reviews', 0);
    }
}
