<?php

namespace Tests\Feature\Admin;

use App\Models\ArtistProfile;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TagAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin');
        Role::findOrCreate('client');
        Role::findOrCreate('artist');
    }

    public function test_store_creates_tag_when_called_by_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->postJson(route('admin.tag.store'), [
            'name' => 'Test Tag',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('tags', [
            'name' => 'Test Tag',
        ]);
    }

    public function test_store_forbids_anonymous_user(): void
    {
        $response = $this->postJson(route('admin.tag.store'), [
            'name' => 'Test Tag',
        ]);
        $response->assertUnauthorized();
    }

    public function test_store_forbids_authenticated_client(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        Sanctum::actingAs($client);

        $response = $this->postJson(route('admin.tag.store'), [
            'name' => 'Test Tag',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('tags', [
            'name' => 'Test Tag',
        ]);
    }

    public function test_store_forbids_authenticated_artist(): void
    {
        $artist = User::factory()->create();
        $artist->assignRole('artist');

        Sanctum::actingAs($artist);

        $response = $this->postJson(route('admin.tag.store'), [
            'name' => 'Test Tag',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('tags', [
            'name' => 'Test Tag',
        ]);
    }

    public function test_store_returns_validation_errors_when_name_is_required(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->postJson(route('admin.tag.store'), [
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

        $response = $this->postJson(route('admin.tag.store'), [
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

        Tag::factory()->create(['name' => 'Test Tag']);

        $response = $this->postJson(route('admin.tag.store'), [
            'name' => 'Test Tag',
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_invalidates_tags_cache(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        Tag::factory()->create();
        $this->getJson(route('tag.index'))->assertOk();
        $this->assertTrue(Cache::has('tags'));

        $this->postJson(route('admin.tag.store'), [
            'name' => 'Test Tag',
        ])->assertCreated();

        $this->assertFalse(Cache::has('tags'));
    }

    public function test_update_updates_tag_when_called_by_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        $response = $this->patchJson(route('admin.tag.update', $tag), [
            'name' => 'Updated Tag',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('tags', [
            'name' => 'Updated Tag',
        ]);
    }

    public function test_update_forbids_anonymous_user(): void
    {
        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        $response = $this->patchJson(route('admin.tag.update', $tag), [
            'name' => 'Updated Tag',
        ]);

        $response->assertUnauthorized();
        $this->assertDatabaseMissing('tags', [
            'name' => 'Updated Tag',
        ]);
    }

    public function test_update_forbids_authenticated_client(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        Sanctum::actingAs($client);

        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        $response = $this->patchJson(route('admin.tag.update', $tag), [
            'name' => 'Updated Tag',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('tags', [
            'name' => 'Updated Tag',
        ]);
    }

    public function test_update_forbids_authenticated_artist(): void
    {
        $artist = User::factory()->create();
        $artist->assignRole('artist');

        Sanctum::actingAs($artist);

        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        $response = $this->patchJson(route('admin.tag.update', $tag), [
            'name' => 'Updated Tag',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('tags', [
            'name' => 'Updated Tag',
        ]);
    }

    public function test_update_returns_validation_errors_when_name_is_required(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        $response = $this->patchJson(route('admin.tag.update', $tag), [
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

        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        $response = $this->patchJson(route('admin.tag.update', $tag), [
            'name' => Str::random(256),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_update_returns_404_when_tag_does_not_exist(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->patchJson(route('admin.tag.update', 999), [
            'name' => 'Updated Tag',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('tags', [
            'id' => 999,
        ]);
    }

    public function test_update_return_validation_errors_when_name_is_already_taken(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $tag = Tag::factory()->create(['name' => 'Test Tag']);
        $tag2 = Tag::factory()->create(['name' => 'Test Tag 2']);

        $response = $this->patchJson(route('admin.tag.update', $tag), [
            'name' => 'Test Tag 2',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('name');
    }

    public function test_update_invalidates_tags_cache(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $tag = Tag::factory()->create(['name' => 'Test Tag']);
        $this->getJson(route('tag.index'))->assertOk();
        $this->assertTrue(Cache::has('tags'));

        $this->patchJson(route('admin.tag.update', $tag), [
            'name' => 'Updated Tag',
        ])->assertOk();

        $this->assertFalse(Cache::has('tags'));
    }

    public function test_destroy_removes_tag_when_called_by_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        $response = $this->deleteJson(route('admin.tag.destroy', $tag));

        $response->assertOk();
        $this->assertDatabaseMissing('tags', [
            'id' => $tag->id,
        ]);
    }

    public function test_destroy_forbids_anonymous_user(): void
    {
        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        $response = $this->deleteJson(route('admin.tag.destroy', $tag));
        $response->assertUnauthorized();
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
        ]);
    }

    public function test_destroy_forbids_authenticated_client(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        Sanctum::actingAs($client);

        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        $response = $this->deleteJson(route('admin.tag.destroy', $tag));

        $response->assertForbidden();
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
        ]);
    }

    public function test_destroy_forbids_authenticated_artist(): void
    {
        $artist = User::factory()->create();
        $artist->assignRole('artist');

        Sanctum::actingAs($artist);

        $tag = Tag::factory()->create(['name' => 'Test Tag']);

        $response = $this->deleteJson(route('admin.tag.destroy', $tag));

        $response->assertForbidden();
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
        ]);
    }

    public function test_destroy_returns_404_when_tag_does_not_exist(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->deleteJson(route('admin.tag.destroy', 999));

        $response->assertNotFound();
        $this->assertDatabaseMissing('tags', [
            'id' => 999,
        ]);
    }

    public function test_destroy_fails_when_tag_is_assigned_to_an_artist(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $tag = Tag::factory()->create(['name' => 'Test Tag']);
        $artist = ArtistProfile::factory()->create();
        $artist->tags()->attach($tag);

        $response = $this->deleteJson(route('admin.tag.destroy', $tag));
        $response->assertStatus(400);
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
        ]);
    }

    public function test_destroy_invalidates_tags_cache(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $tag = Tag::factory()->create(['name' => 'Test Tag']);
        $this->getJson(route('tag.index'))->assertOk();
        $this->assertTrue(Cache::has('tags'));

        $this->deleteJson(route('admin.tag.destroy', $tag))->assertOk();
        $this->assertFalse(Cache::has('tags'));
    }
}
