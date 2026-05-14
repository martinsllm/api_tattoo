<?php

namespace Tests\Feature\Admin;

use App\Models\ArtistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArtistAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');
        Role::findOrCreate('artist');
        Role::findOrCreate('admin');
    }

    public function test_deactivate_marks_artist_as_inactive_when_called_by_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $artist = ArtistProfile::factory()->create(['is_active' => true]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson(route('admin.artist.deactivate', $artist->id));

        $response->assertOk()
            ->assertJson(['message' => 'Artist deactivated successfully']);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => false,
        ]);
    }

    public function test_activate_marks_artist_as_active_when_called_by_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $artist = ArtistProfile::factory()->inactive()->create();

        Sanctum::actingAs($admin);

        $response = $this->patchJson(route('admin.artist.activate', $artist->id));

        $response->assertOk()
            ->assertJson(['message' => 'Artist activated successfully']);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => true,
        ]);
    }

    public function test_deactivate_forbids_anonymous_user(): void
    {
        $artist = ArtistProfile::factory()->create(['is_active' => true]);

        $response = $this->patchJson(route('admin.artist.deactivate', $artist->id));

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => true,
        ]);
    }

    public function test_deactivate_forbids_authenticated_client(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $artist = ArtistProfile::factory()->create(['is_active' => true]);

        Sanctum::actingAs($client);

        $response = $this->patchJson(route('admin.artist.deactivate', $artist->id));

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => true,
        ]);
    }

    public function test_deactivate_forbids_authenticated_artist(): void
    {
        $artist = ArtistProfile::factory()->create(['is_active' => true]);
        $artist->user->assignRole('artist');

        Sanctum::actingAs($artist->user);

        $response = $this->patchJson(route('admin.artist.deactivate', $artist->id));

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => true,
        ]);
    }

    public function test_activate_forbids_anonymous_user(): void
    {
        $artist = ArtistProfile::factory()->inactive()->create();

        $response = $this->patchJson(route('admin.artist.activate', $artist->id));

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => false,
        ]);
    }

    public function test_activate_forbids_authenticated_artist(): void
    {
        $artist = ArtistProfile::factory()->inactive()->create();
        $artist->user->assignRole('artist');

        Sanctum::actingAs($artist->user);

        $response = $this->patchJson(route('admin.artist.activate', $artist->id));

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => false,
        ]);
    }

    public function test_activate_forbids_authenticated_client(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $artist = ArtistProfile::factory()->inactive()->create();

        Sanctum::actingAs($client);

        $response = $this->patchJson(route('admin.artist.activate', $artist->id));

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);

        $this->assertDatabaseHas('artist_profiles', [
            'id' => $artist->id,
            'is_active' => false,
        ]);
    }

    public function test_deactivate_returns_404_when_artist_does_not_exist(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->patchJson(route('admin.artist.deactivate', 9999));

        $response->assertStatus(404)
            ->assertJson(['message' => 'Resource not found']);
    }
}
