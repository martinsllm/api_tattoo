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

    public function test_index_lists_active_and_inactive_artists_when_called_by_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $active = ArtistProfile::factory()->create(['studio_name' => 'Ativo Admin']);
        $inactive = ArtistProfile::factory()->inactive()->create(['studio_name' => 'Inativo Admin']);

        Sanctum::actingAs($admin);

        $response = $this->getJson(route('admin.artist.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.is_active', true)
            ->assertJsonPath('data.1.is_active', false);

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($active->id));
        $this->assertTrue($ids->contains($inactive->id));
    }

    public function test_index_filters_inactive_artists_when_is_active_is_false(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        ArtistProfile::factory()->create(['studio_name' => 'Ativo']);
        $inactive = ArtistProfile::factory()->inactive()->create(['studio_name' => 'Inativo']);

        Sanctum::actingAs($admin);

        $response = $this->getJson(route('admin.artist.index', ['is_active' => false]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inactive->id)
            ->assertJsonPath('data.0.is_active', false);
    }

    public function test_index_forbids_anonymous_user(): void
    {
        ArtistProfile::factory()->create();

        $response = $this->getJson(route('admin.artist.index'));

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);
    }

    public function test_index_forbids_authenticated_client(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        Sanctum::actingAs($client);

        $response = $this->getJson(route('admin.artist.index'));

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);
    }

    public function test_index_forbids_authenticated_artist(): void
    {
        $artist = ArtistProfile::factory()->create();
        $artist->user->assignRole('artist');

        Sanctum::actingAs($artist->user);

        $response = $this->getJson(route('admin.artist.index'));

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);
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

    public function test_deactivate_records_audit_log_with_actor_and_target(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $artist = ArtistProfile::factory()->create(['is_active' => true]);

        Sanctum::actingAs($admin);

        $this->patchJson(route('admin.artist.deactivate', $artist->id))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'artist.deactivate',
            'auditable_type' => ArtistProfile::class,
            'auditable_id' => $artist->id,
        ]);
    }

    public function test_activate_records_audit_log_with_actor_and_target(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $artist = ArtistProfile::factory()->inactive()->create();

        Sanctum::actingAs($admin);

        $this->patchJson(route('admin.artist.activate', $artist->id))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'artist.activate',
            'auditable_type' => ArtistProfile::class,
            'auditable_id' => $artist->id,
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
