<?php

namespace Tests\Feature\Admin;

use App\Models\ArtistProfile;
use App\Models\Style;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StyleAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin');
        Role::findOrCreate('client');
        Role::findOrCreate('artist');
    }

    public function test_store_creates_style_when_called_by_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->postJson(route('admin.style.store'), [
            'name' => 'Test Style',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('styles', [
            'name' => 'Test Style',
        ]);
    }

    public function test_store_forbids_anonymous_user(): void
    {
        $response = $this->postJson(route('admin.style.store'), [
            'name' => 'Test Style',
        ]);

        $response->assertUnauthorized();
    }

    public function test_store_forbids_authenticated_client(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        Sanctum::actingAs($client);

        $response = $this->postJson(route('admin.style.store'), [
            'name' => 'Test Style',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('styles', [
            'name' => 'Test Style',
        ]);
    }

    public function test_store_forbids_authenticated_artist(): void
    {
        $artist = User::factory()->create();
        $artist->assignRole('artist');

        Sanctum::actingAs($artist);

        $response = $this->postJson(route('admin.style.store'), [
            'name' => 'Test Style',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('styles', [
            'name' => 'Test Style',
        ]);
    }

    public function test_store_returns_validation_errors_when_name_is_required(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->postJson(route('admin.style.store'), [
            'name' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_returns_validation_errors_when_name_is_too_long(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->postJson(route('admin.style.store'), [
            'name' => Str::random(256),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_returns_validation_errors_when_name_is_already_taken(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        Style::factory()->create(['name' => 'Existing Style']);

        $response = $this->postJson(route('admin.style.store'), [
            'name' => 'Existing Style',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_invalidates_styles_cache(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Style::factory()->create();
        $this->getJson(route('style.index'))->assertOk();
        $this->assertTrue(Cache::has('styles'));

        Sanctum::actingAs($admin);

        $this->postJson(route('admin.style.store'), [
            'name' => 'New Style',
        ])->assertCreated();

        $this->assertFalse(Cache::has('styles'));
    }

    public function test_update_updates_style_when_called_by_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $style = Style::factory()->create();

        $response = $this->patchJson(route('admin.style.update', $style->id), [
            'name' => 'Updated Style',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('styles', [
            'name' => 'Updated Style',
        ]);
    }

    public function test_update_forbids_anonymous_user(): void
    {
        $style = Style::factory()->create();

        $response = $this->patchJson(route('admin.style.update', $style->id), [
            'name' => 'Updated Style',
        ]);

        $response->assertUnauthorized();
        $this->assertDatabaseMissing('styles', [
            'name' => 'Updated Style',
        ]);
    }

    public function test_update_forbids_authenticated_client(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        Sanctum::actingAs($client);

        $style = Style::factory()->create();

        $response = $this->patchJson(route('admin.style.update', $style->id), [
            'name' => 'Updated Style',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('styles', [
            'name' => 'Updated Style',
        ]);
    }

    public function test_update_forbids_authenticated_artist(): void
    {
        $artist = User::factory()->create();
        $artist->assignRole('artist');

        Sanctum::actingAs($artist);

        $style = Style::factory()->create();

        $response = $this->patchJson(route('admin.style.update', $style->id), [
            'name' => 'Updated Style',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('styles', [
            'name' => 'Updated Style',
        ]);
    }

    public function test_update_returns_validation_errors_when_name_is_required(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $style = Style::factory()->create();

        $response = $this->patchJson(route('admin.style.update', $style->id), [
            'name' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_update_returns_validation_errors_when_name_is_too_long(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $style = Style::factory()->create();

        $response = $this->patchJson(route('admin.style.update', $style->id), [
            'name' => Str::random(256),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_update_returns_404_when_style_does_not_exist(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->patchJson(route('admin.style.update', 999), [
            'name' => 'Updated Style',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('styles', [
            'id' => 999,
        ]);
    }

    public function test_update_return_validation_errors_when_name_is_already_taken(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $style1 = Style::factory()->create(['name' => 'Style 1']);
        $style2 = Style::factory()->create(['name' => 'Style 2']);

        $response = $this->patchJson(route('admin.style.update', $style1->id), [
            'name' => 'Style 2',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_update_invalidates_styles_cache(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $style = Style::factory()->create();
        $this->getJson(route('style.index'))->assertOk();
        $this->assertTrue(Cache::has('styles'));

        Sanctum::actingAs($admin);

        $this->patchJson(route('admin.style.update', $style->id), [
            'name' => 'Updated Style',
        ])->assertOk();

        $this->assertFalse(Cache::has('styles'));
    }

    public function test_destroy_removes_style_when_called_by_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $style = Style::factory()->create();

        $response = $this->deleteJson(route('admin.style.destroy', $style->id));

        $response->assertOk();
        $this->assertDatabaseMissing('styles', [
            'id' => $style->id,
        ]);
    }

    public function test_destroy_forbids_anonymous_user(): void
    {
        $style = Style::factory()->create();

        $response = $this->deleteJson(route('admin.style.destroy', $style->id));

        $response->assertUnauthorized();
        $this->assertDatabaseHas('styles', [
            'id' => $style->id,
        ]);
    }

    public function test_destroy_forbids_authenticated_client(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        Sanctum::actingAs($client);

        $style = Style::factory()->create();

        $response = $this->deleteJson(route('admin.style.destroy', $style->id));

        $response->assertForbidden();
        $this->assertDatabaseHas('styles', [
            'id' => $style->id,
        ]);
    }

    public function test_destroy_forbids_authenticated_artist(): void
    {
        $artist = User::factory()->create();
        $artist->assignRole('artist');

        Sanctum::actingAs($artist);

        $style = Style::factory()->create();

        $response = $this->deleteJson(route('admin.style.destroy', $style->id));

        $response->assertForbidden();
        $this->assertDatabaseHas('styles', [
            'id' => $style->id,
        ]);
    }

    public function test_destroy_returns_404_when_style_does_not_exist(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->deleteJson(route('admin.style.destroy', 999));

        $response->assertNotFound();
        $this->assertDatabaseMissing('styles', [
            'id' => 999,
        ]);
    }

    public function test_destroy_fails_when_style_is_assigned_to_an_artist(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $style = Style::factory()->create();

        $artist = ArtistProfile::factory()->create();
        $artist->styles()->attach($style->id);

        $response = $this->deleteJson(route('admin.style.destroy', $style->id));

        $response->assertStatus(400);
        $this->assertDatabaseHas('styles', [
            'id' => $style->id,
        ]);
    }

    public function test_destroy_invalidates_styles_cache(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $style = Style::factory()->create();
        $this->getJson(route('style.index'))->assertOk();
        $this->assertTrue(Cache::has('styles'));

        Sanctum::actingAs($admin);

        $this->deleteJson(route('admin.style.destroy', $style->id))->assertOk();

        $this->assertFalse(Cache::has('styles'));
    }
}
