<?php

namespace Tests\Feature\Admin;

use App\Models\ArtistProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJson([
                'message' => 'Metrics retrieved successfully',
            ])
            ->assertJsonPath('data.total_artists', 2)
            ->assertJsonPath('data.total_reviews', 3)
            ->assertJsonPath('data.total_favorites', 2);
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
