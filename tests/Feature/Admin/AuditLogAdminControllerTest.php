<?php

namespace Tests\Feature\Admin;

use App\Models\ArtistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');
        Role::findOrCreate('artist');
        Role::findOrCreate('admin');
    }

    public function test_index_returns_paginated_audit_logs_for_admin(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $response = $this->getJson(route('admin.audit-log.index'));

        $response->assertOk()
            ->assertJsonPath('message', 'Audit logs fetched successfully')
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'total', 'per_page', 'last_page'],
            ]);
    }

    public function test_index_includes_audit_log_after_admin_deactivates_artist(): void
    {
        $admin = User::factory()->admin()->create();

        $artist = ArtistProfile::factory()->create(['is_active' => true]);

        Sanctum::actingAs($admin);

        $this->patchJson(route('admin.artist.deactivate', $artist->id))->assertOk();

        $response = $this->getJson(route('admin.audit-log.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'artist.deactivate')
            ->assertJsonPath('data.0.auditable_id', $artist->id)
            ->assertJsonPath('data.0.actor.id', $admin->id);
    }

    public function test_index_forbids_anonymous_user(): void
    {
        $response = $this->getJson(route('admin.audit-log.index'));

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_index_forbids_authenticated_client(): void
    {
        $client = User::factory()->client()->create();

        Sanctum::actingAs($client);

        $response = $this->getJson(route('admin.audit-log.index'));

        $response->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');
    }

    public function test_index_forbids_authenticated_artist(): void
    {
        $artistProfile = ArtistProfile::factory()->create();
        $artistProfile->user->assignRole('artist');

        Sanctum::actingAs($artistProfile->user);

        $response = $this->getJson(route('admin.audit-log.index'));

        $response->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');
    }
}
