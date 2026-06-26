<?php

namespace Tests\Feature\Favorite;

use App\Models\ArtistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FavoriteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
    }

    public function test_index_returns_pagination_metadata_at_root_level(): void
    {
        $user = User::factory()->create();
        $artist = ArtistProfile::factory()->create();
        $user->favorites()->attach($artist->id);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('favorite.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'total', 'per_page', 'last_page'],
                'message',
            ]);
    }

    public function test_toggle_adds_then_removes_artist_from_favorites(): void
    {
        $user = User::factory()->create();

        $artist = ArtistProfile::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson(route('artist.favorite.toggle', $artist->id))
            ->assertStatus(200)
            ->assertJson(['message' => 'Added to favorites']);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'artist_profile_id' => $artist->id,
        ]);
        $this->assertDatabaseCount('favorites', 1);

        $this->postJson(route('artist.favorite.toggle', $artist->id))
            ->assertStatus(200)
            ->assertJson(['message' => 'Removed from favorites']);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'artist_profile_id' => $artist->id,
        ]);
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_toggle_requires_authentication(): void
    {
        $artist = ArtistProfile::factory()->create();

        $response = $this->postJson(route('artist.favorite.toggle', $artist->id));

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated',
            ]);

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_toggle_forbids_admin_from_favoriting(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $artist = ArtistProfile::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson(route('artist.favorite.toggle', $artist->id));

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden',
            ]);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $admin->id,
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

        $response = $this->postJson(route('artist.favorite.toggle', $artist->id));

        $response->assertStatus(400)
            ->assertJson(['message' => 'You cannot favorite yourself.']);

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

        $response = $this->postJson(route('artist.favorite.toggle', $artist->id));

        $response->assertStatus(404)
            ->assertJson(['message' => 'Resource not found']);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'artist_profile_id' => $artist->id,
        ]);

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_index_respects_per_page_parameter(): void
    {
        $user = User::factory()->create();
        $artists = ArtistProfile::factory()->count(5)->create();
        $user->favorites()->attach($artists->pluck('id'));

        Sanctum::actingAs($user);

        $response = $this->getJson(route('favorite.index', ['per_page' => 3]));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 3);
    }

    public function test_index_rejects_per_page_above_maximum(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('favorite.index', ['per_page' => 51]));

        $response->assertStatus(422);
    }

    public function test_index_rejects_invalid_per_page(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson(route('favorite.index', ['per_page' => 'abc']));

        $response->assertStatus(422);
    }

    public function test_authenticated_mutation_route_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $artist = ArtistProfile::factory()->create();

        Sanctum::actingAs($user);

        // esgotar o limite de 30 requisições permitidas
        for ($i = 0; $i < 30; $i++) {
            $this->postJson(route('artist.favorite.toggle', $artist->id))
                ->assertStatus(200);
        }

        // a próxima requisição deve ser bloqueada
        $this->postJson(route('artist.favorite.toggle', $artist->id))
            ->assertStatus(429)
            ->assertJson(['message' => 'Too many requests']);
    }
}
