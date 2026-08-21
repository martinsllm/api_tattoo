<?php

namespace Tests\Feature\Admin;

use App\Models\ArtistProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MetricsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');
        Role::findOrCreate('artist');
        Role::findOrCreate('admin');
    }

    public function test_metrics_returns_totals_when_called_by_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $artists = ArtistProfile::factory()->count(2)->create();
        Review::factory()->count(3)->create([
            'artist_profile_id' => $artists->first()->id,
        ]);

        $client = User::factory()->create();
        $client->favorites()->attach($artists->pluck('id'));

        Sanctum::actingAs($admin);

        $response = $this->getJson(route('admin.metrics'));

        $response->assertOk()
            ->assertJson(['message' => 'Metrics retrieved successfully'])
            ->assertJsonStructure([
                'data' => [
                    'total_artists',
                    'total_reviews',
                    'total_favorites',
                    'active_artists',
                    'inactive_artists',
                    'reviews_this_month',
                    'favorites_this_month',
                ],
            ])
            ->assertJsonPath('data.total_artists', 2)
            ->assertJsonPath('data.total_reviews', 3)
            ->assertJsonPath('data.total_favorites', 2);
    }

    public function test_metrics_filters_reviews_and_favorites_by_current_month_dates(): void
    {
        $this->travelTo('2026-08-15 12:00:00');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $activeArtist = ArtistProfile::factory()->create(['is_active' => true]);
        ArtistProfile::factory()->create(['is_active' => true]);
        ArtistProfile::factory()->inactive()->create();

        Review::factory()->count(2)->create([
            'artist_profile_id' => $activeArtist->id,
            'created_at' => now(),
        ]);
        Review::factory()->create([
            'artist_profile_id' => $activeArtist->id,
            'created_at' => now()->subMonth(),
        ]);

        $client = User::factory()->create();
        $client->favorites()->attach($activeArtist->id);
        DB::table('favorites')
            ->where('user_id', $client->id)
            ->where('artist_profile_id', $activeArtist->id)
            ->update(['created_at' => now()]);

        $otherClient = User::factory()->create();
        $otherClient->favorites()->attach($activeArtist->id);
        DB::table('favorites')
            ->where('user_id', $otherClient->id)
            ->where('artist_profile_id', $activeArtist->id)
            ->update(['created_at' => now()->subMonth()]);

        Sanctum::actingAs($admin);

        $response = $this->getJson(route('admin.metrics'));

        $response->assertOk()
            ->assertJsonPath('data.active_artists', 2)
            ->assertJsonPath('data.inactive_artists', 1)
            ->assertJsonPath('data.reviews_this_month', 2)
            ->assertJsonPath('data.favorites_this_month', 1);
    }

    public function test_metrics_forbids_anonymous_user(): void
    {
        ArtistProfile::factory()->create();

        $response = $this->getJson(route('admin.metrics'));

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);
    }

    public function test_metrics_forbids_authenticated_client(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        Sanctum::actingAs($client);

        $response = $this->getJson(route('admin.metrics'));

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);
    }

    public function test_metrics_forbids_authenticated_artist(): void
    {
        $artist = ArtistProfile::factory()->create();
        $artist->user->assignRole('artist');

        Sanctum::actingAs($artist->user);

        $response = $this->getJson(route('admin.metrics'));

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);
    }
}
