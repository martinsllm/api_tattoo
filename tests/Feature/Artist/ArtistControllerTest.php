<?php

namespace Tests\Feature\Artist;

use App\Models\ArtistProfile;
use App\Models\Style;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArtistControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_artist_profile_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $payload = [
            'studio_name' => 'Tinta Preta Studio',
            'bio' => 'Especialistas em blackwork.',
            'city' => 'Curitiba',
            'state' => 'PR',
            'latitude' => -25.4284,
            'longitude' => -49.2733,
        ];

        $response = $this->postJson(route('artist.store'), $payload);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'studio_name',
                    'city',
                    'state',
                    'location' => ['latitude', 'longitude'],
                    'created_at',
                ],
                'message',
            ])
            ->assertJsonPath('data.studio_name', 'Tinta Preta Studio');

        $this->assertDatabaseHas('artist_profiles', [
            'user_id' => $user->id,
            'studio_name' => 'Tinta Preta Studio',
            'city' => 'Curitiba',
            'is_active' => true,
        ]);
    }

    public function test_store_rejects_when_user_already_has_artist_profile(): void
    {
        $user = User::factory()->create();
        ArtistProfile::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(route('artist.store'), [
            'studio_name' => 'Segundo Estúdio',
            'city' => 'Curitiba',
            'state' => 'PR',
            'latitude' => -25.4284,
            'longitude' => -49.2733,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'User already has an artist profile',
            ]);

        $this->assertSame(1, ArtistProfile::where('user_id', $user->id)->count());
    }

    public function test_update_allows_owner_to_change_their_profile(): void
    {
        $owner = User::factory()->create();
        $artist = ArtistProfile::factory()->for($owner)->create([
            'studio_name' => 'Nome Antigo',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->putJson(route('artist.update', $artist->id), [
            'studio_name' => 'Nome Novo',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.studio_name', 'Nome Novo');

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'studio_name' => 'Nome Novo',
        ]);
    }

    public function test_update_forbids_non_owner_from_changing_profile(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $artist = ArtistProfile::factory()->for($owner)->create([
            'studio_name' => 'Nome Antigo',
        ]);

        Sanctum::actingAs($intruder);

        $response = $this->putJson(route('artist.update', $artist->id), [
            'studio_name' => 'Tentativa Invasor',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden',
            ]);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'studio_name' => 'Nome Antigo',
        ]);
    }

    public function test_show_returns_active_artist(): void
    {
        $artist = ArtistProfile::factory()->create([
            'studio_name' => 'Estúdio Visível',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson(route('artist.show', $artist->id));

        $response->assertOk()
            ->assertJsonPath('data.id', $artist->id)
            ->assertJsonPath('data.studio_name', 'Estúdio Visível');
    }

    public function test_show_returns_404_for_inactive_artist(): void
    {
        $artist = ArtistProfile::factory()->inactive()->create();

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson(route('artist.show', $artist->id));

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Resource not found',
            ]);
    }

    public function test_index_returns_only_active_artists(): void
    {
        $active = ArtistProfile::factory()->create(['studio_name' => 'Ativo']);
        ArtistProfile::factory()->inactive()->create(['studio_name' => 'Inativo']);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson(route('artist.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonMissing(['studio_name' => 'Inativo']);
    }

    public function test_index_filters_by_styles(): void
    {
        $blackwork = Style::factory()->create(['name' => 'Blackwork']);
        $aquarela = Style::factory()->create(['name' => 'Aquarela']);

        $artistBlackwork = ArtistProfile::factory()->create(['studio_name' => 'Tinta Preta']);
        $artistBlackwork->styles()->attach($blackwork);

        $artistAquarela = ArtistProfile::factory()->create(['studio_name' => 'Cores Vivas']);
        $artistAquarela->styles()->attach($aquarela);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson(route('artist.index', ['styles' => [$blackwork->id]]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $artistBlackwork->id)
            ->assertJsonMissing(['studio_name' => 'Cores Vivas']);
    }
}
