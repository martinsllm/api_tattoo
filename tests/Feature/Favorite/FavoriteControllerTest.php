<?php

namespace Tests\Feature\Favorite;

use App\Models\ArtistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoriteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_adds_then_removes_artist_from_favorites(): void
    {
        $user = User::factory()->create();

        $artist = ArtistProfile::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/artists/{$artist->id}/favorite")
            ->assertStatus(200)
            ->assertJson(['message' => 'Added to favorites']);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'artist_profile_id' => $artist->id,
        ]);
        $this->assertDatabaseCount('favorites', 1);

        $this->postJson("/api/artists/{$artist->id}/favorite")
            ->assertStatus(200)
            ->assertJson(['message' => 'Removed from favorites']);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'artist_profile_id' => $artist->id,
        ]);
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_toggle_rejects_authenticated_user_from_favoriting_their_own_artist(): void
    {
        $user = User::factory()->create();

        $artist = ArtistProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/artists/{$artist->id}/favorite");

        $response->assertStatus(400)
            ->assertJson(['message' => 'You cannot favorite yourself']);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'artist_profile_id' => $artist->id,
        ]);

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_toggle_rejects_authenticated_user_from_favoriting_an_inactive_artist(): void
    {
        $user = User::factory()->create();

        $artist = ArtistProfile::factory()->inactive()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/artists/{$artist->id}/favorite");

        $response->assertStatus(404)
            ->assertJson(['message' => 'Resource not found']);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'artist_profile_id' => $artist->id,
        ]);

        $this->assertDatabaseCount('favorites', 0);
    }
}
