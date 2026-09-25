<?php

namespace Tests\E2E;

use App\Models\ArtistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientFavoriteFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');
    }

    public function test_client_can_login_and_favorite_an_artist(): void
    {
        $client = User::factory()->create([
            'email' => 'client@example.com',
            'password' => 'password',
        ]);

        $artist = ArtistProfile::factory()->create();

        $client->assignRole('client');

        $loginResponse = $this->postJson(route('auth.login'), [
            'email' => $client->email,
            'password' => 'password',
        ]);

        $token = $loginResponse->json('data.token');

        $loginResponse->assertOk()
            ->assertJsonPath('data.user.roles', ['client']);

        $this->assertNotEmpty($token);

        $this->withToken($token);

        $favoriteResponse = $this->postJson(route('artist.favorite.toggle', $artist->id));

        $favoriteResponse->assertOk()
            ->assertJsonPath('message', 'Added to favorites');

        $this->assertDatabaseHas('favorites', [
            'user_id' => $client->id,
            'artist_profile_id' => $artist->id,
        ]);

        $getFavoritesResponse = $this->getJson(route('favorite.index'));

        $getFavoritesResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $artist->id)
            ->assertJsonPath('data.0.studio_name', $artist->studio_name);
    }
}
