<?php

namespace Tests\Feature\Artist;

use App\Models\ArtistProfile;
use App\Models\Review;
use App\Models\Style;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArtistControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');
        Role::findOrCreate('artist');
        Role::findOrCreate('admin');
    }

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

    public function test_store_promotes_user_to_artist_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        Sanctum::actingAs($user);

        $response = $this->postJson(route('artist.store'), [
            'studio_name' => 'Tinta Preta Studio',
            'city' => 'Curitiba',
            'state' => 'PR',
            'latitude' => -25.4284,
            'longitude' => -49.2733,
        ]);

        $response->assertOk();

        $user->refresh();
        $this->assertTrue($user->hasRole('artist'));
        $this->assertFalse($user->hasRole('client'));
    }

    public function test_update_allows_owner_to_change_their_profile(): void
    {
        $owner = User::factory()->create();
        $artist = ArtistProfile::factory()->for($owner)->create([
            'studio_name' => 'Nome Antigo',
        ]);

        Sanctum::actingAs($owner);

        $response = $this->patchJson(route('artist.update', $artist->id), [
            'studio_name' => 'Nome Novo',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.studio_name', 'Nome Novo');

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'studio_name' => 'Nome Novo',
        ]);
    }

    public function test_update_requires_authentication(): void
    {
        $owner = User::factory()->create();
        $artist = ArtistProfile::factory()->for($owner)->create([
            'studio_name' => 'Nome Antigo',
        ]);

        $response = $this->patchJson(route('artist.update', $artist->id), [
            'studio_name' => 'Tentativa Anonima',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated',
            ]);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'studio_name' => 'Nome Antigo',
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

        $response = $this->patchJson(route('artist.update', $artist->id), [
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

    public function test_update_forbids_admin_from_changing_other_artist_profile(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $artist = ArtistProfile::factory()->for($owner)->create([
            'studio_name' => 'Nome Antigo',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson(route('artist.update', $artist->id), [
            'studio_name' => 'Admin Tentando Editar',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden');

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

    public function test_index_returns_pagination_metadata_at_root_level(): void
    {
        ArtistProfile::factory()->count(3)->create();

        $response = $this->getJson(route('artist.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'total', 'per_page', 'last_page'],
                'message',
            ]);
    }

    public function test_index_filters_by_city(): void
    {
        $spArtist = ArtistProfile::factory()->create(['studio_name' => 'SP Studio', 'city' => 'São Paulo']);
        ArtistProfile::factory()->create(['studio_name' => 'RJ Studio', 'city' => 'Rio de Janeiro']);

        $response = $this->getJson(route('artist.index', ['city' => 'São Paulo']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $spArtist->id)
            ->assertJsonMissing(['studio_name' => 'RJ Studio']);
    }

    public function test_index_filters_by_state(): void
    {
        $prArtist = ArtistProfile::factory()->create(['studio_name' => 'PR Studio', 'state' => 'PR']);
        ArtistProfile::factory()->create(['studio_name' => 'SP Studio', 'state' => 'SP']);

        $response = $this->getJson(route('artist.index', ['state' => 'PR']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $prArtist->id)
            ->assertJsonMissing(['studio_name' => 'SP Studio']);
    }

    public function test_index_filters_by_studio_name(): void
    {
        $blackwork = ArtistProfile::factory()->create(['studio_name' => 'Blackwork SP']);
        ArtistProfile::factory()->create(['studio_name' => 'Aquarela Studio']);

        $response = $this->getJson(route('artist.index', ['q' => 'Blackwork']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $blackwork->id)
            ->assertJsonMissing(['studio_name' => 'Aquarela Studio']);
    }

    public function test_index_filters_combined_city_and_studio_name(): void
    {
        $target = ArtistProfile::factory()->create(['studio_name' => 'Old School SP', 'city' => 'São Paulo']);
        ArtistProfile::factory()->create(['studio_name' => 'Old School RJ', 'city' => 'Rio de Janeiro']);
        ArtistProfile::factory()->create(['studio_name' => 'Outro Studio', 'city' => 'São Paulo']);

        $response = $this->getJson(route('artist.index', ['city' => 'São Paulo', 'q' => 'Old School']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id);
    }

    public function test_deactivate_allows_owner_to_deactivate_their_profile(): void
    {
        $owner = User::factory()->create();
        $artist = ArtistProfile::factory()->for($owner)->create(['is_active' => true]);

        Sanctum::actingAs($owner);

        $response = $this->patchJson(route('artist.deactivate', $artist->id));

        $response->assertOk()
            ->assertJson(['message' => 'Artist deactivated successfully']);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => false,
        ]);
    }

    public function test_deactivate_forbids_non_owner(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $artist = ArtistProfile::factory()->for($owner)->create(['is_active' => true]);

        Sanctum::actingAs($intruder);

        $response = $this->patchJson(route('artist.deactivate', $artist->id));

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => true,
        ]);
    }

    public function test_deactivate_requires_authentication(): void
    {
        $artist = ArtistProfile::factory()->create(['is_active' => true]);

        $response = $this->patchJson(route('artist.deactivate', $artist->id));

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => true,
        ]);
    }

    public function test_activate_allows_owner_to_reactivate_their_profile(): void
    {
        $owner = User::factory()->create();
        $artist = ArtistProfile::factory()->for($owner)->inactive()->create();

        Sanctum::actingAs($owner);

        $response = $this->patchJson(route('artist.activate', $artist->id));

        $response->assertOk()
            ->assertJson(['message' => 'Artist activated successfully']);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => true,
        ]);
    }

    public function test_activate_forbids_non_owner(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $artist = ArtistProfile::factory()->for($owner)->inactive()->create();

        Sanctum::actingAs($intruder);

        $response = $this->patchJson(route('artist.activate', $artist->id));

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => false,
        ]);
    }

    public function test_activate_requires_authentication(): void
    {
        $artist = ArtistProfile::factory()->inactive()->create();

        $response = $this->patchJson(route('artist.activate', $artist->id));

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => false,
        ]);
    }

    public function test_index_respects_per_page_parameter(): void
    {
        ArtistProfile::factory()->count(5)->create();

        $response = $this->getJson(route('artist.index', ['per_page' => 3]));

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 3);
    }

    public function test_index_rejects_per_page_above_maximum(): void
    {
        $response = $this->getJson(route('artist.index', ['per_page' => 51]));

        $response->assertStatus(422);
    }

    public function test_index_rejects_invalid_per_page(): void
    {
        $response = $this->getJson(route('artist.index', ['per_page' => 'abc']));

        $response->assertStatus(422);
    }

    public function test_index_sorts_by_rating(): void
    {
        $topRated = ArtistProfile::factory()->create(['studio_name' => 'Top Rated']);
        Review::factory()->create(['artist_profile_id' => $topRated->id, 'rating' => 5]);

        $lowRated = ArtistProfile::factory()->create(['studio_name' => 'Low Rated']);
        Review::factory()->create(['artist_profile_id' => $lowRated->id, 'rating' => 1]);

        $response = $this->getJson(route('artist.index', ['sort' => 'rating']));

        $response->assertOk()
            ->assertJsonPath('data.0.id', $topRated->id)
            ->assertJsonPath('data.1.id', $lowRated->id);
    }

    public function test_index_sorts_by_newest(): void
    {
        $older = ArtistProfile::factory()->create([
            'studio_name' => 'Older',
            'created_at' => now()->subDay(),
        ]);
        $newer = ArtistProfile::factory()->create([
            'studio_name' => 'Newer',
            'created_at' => now(),
        ]);

        $response = $this->getJson(route('artist.index', ['sort' => 'newest']));

        $response->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_index_sorts_by_distance(): void
    {
        $nearby = ArtistProfile::factory()->create([
            'studio_name' => 'Nearby',
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);
        ArtistProfile::factory()->create([
            'studio_name' => 'Far Away',
            'latitude' => -22.9068,
            'longitude' => -43.1729,
        ]);

        $response = $this->getJson(route('artist.index', [
            'lat' => -23.5505,
            'lng' => -46.6333,
            'radius' => 500,
            'sort' => 'distance',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.0.id', $nearby->id)
            ->assertJsonPath('data.0.location.distance', 0);
    }

    public function test_index_sort_distance_requires_geo(): void
    {
        $response = $this->getJson(route('artist.index', ['sort' => 'distance']));

        $response->assertStatus(422);
    }

    public function test_index_rejects_invalid_sort(): void
    {
        $response = $this->getJson(route('artist.index', ['sort' => 'name']));

        $response->assertStatus(422);
    }

    public function test_index_is_accessible_without_authentication(): void
    {
        ArtistProfile::factory()->create(['studio_name' => 'Estúdio Público']);

        $response = $this->getJson(route('artist.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.studio_name', 'Estúdio Público');
    }

    public function test_show_is_accessible_without_authentication(): void
    {
        $artist = ArtistProfile::factory()->create(['studio_name' => 'Estúdio Público']);

        $response = $this->getJson(route('artist.show', $artist->id));

        $response->assertOk()
            ->assertJsonPath('data.id', $artist->id)
            ->assertJsonPath('data.studio_name', 'Estúdio Público');
    }

    public function test_show_hides_exact_coordinates_from_anonymous_users(): void
    {
        $artist = ArtistProfile::factory()->create([
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response = $this->getJson(route('artist.show', $artist->id));

        $response->assertOk()
            ->assertJsonPath('data.location.latitude', null)
            ->assertJsonPath('data.location.longitude', null);
    }

    public function test_show_exposes_exact_coordinates_to_authenticated_users(): void
    {
        $artist = ArtistProfile::factory()->create([
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson(route('artist.show', $artist->id));

        $response->assertOk()
            ->assertJsonPath('data.location.latitude', -23.5505)
            ->assertJsonPath('data.location.longitude', -46.6333);
    }

    public function test_show_exposes_exact_coordinates_when_bearer_token_is_sent(): void
    {
        $artist = ArtistProfile::factory()->create([
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('artist.show', $artist->id));

        $response->assertOk()
            ->assertJsonPath('data.location.latitude', -23.5505)
            ->assertJsonPath('data.location.longitude', -46.6333);
    }

    public function test_index_includes_favorites_count(): void
    {
        $artist = ArtistProfile::factory()->create();
        $artist->favoritedBy()->attach(User::factory()->count(2)->create()->pluck('id'));

        $response = $this->getJson(route('artist.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.favorites_count', 2);
    }

    public function test_index_hides_favorites_count_when_zero(): void
    {
        ArtistProfile::factory()->create();

        $response = $this->getJson(route('artist.index'));

        $response->assertOk()
            ->assertJsonMissingPath('data.0.favorites_count');
    }

    public function test_show_includes_favorites_count(): void
    {
        $artist = ArtistProfile::factory()->create();
        $artist->favoritedBy()->attach(User::factory()->create()->id);

        $response = $this->getJson(route('artist.show', $artist->id));

        $response->assertOk()
            ->assertJsonPath('data.favorites_count', 1);
    }

    public function test_show_hides_favorites_count_when_zero(): void
    {
        $artist = ArtistProfile::factory()->create();

        $response = $this->getJson(route('artist.show', $artist->id));

        $response->assertOk()
            ->assertJsonMissingPath('data.favorites_count');
    }

    public function test_show_does_not_include_reviews(): void
    {
        $artist = ArtistProfile::factory()->create();
        Review::factory()->count(3)->create(['artist_profile_id' => $artist->id]);

        $response = $this->getJson(route('artist.show', $artist->id));

        $response->assertOk()
            ->assertJsonMissingPath('data.reviews');
    }
}
