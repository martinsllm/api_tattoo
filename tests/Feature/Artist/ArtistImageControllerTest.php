<?php

namespace Tests\Feature\Artist;

use App\Models\ArtistImage;
use App\Models\ArtistProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ArtistImageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
    }

    public function test_store_creates_artist_images_for_authenticated_user(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $artist = ArtistProfile::factory()->for($user)->create();

        $payload = [
            'images' => [
                UploadedFile::fake()->image('image1.jpg'),
            ],
        ];

        $response = $this->postJson(route('artist.image.store', $artist->id), $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'url', 'is_main', 'created_at'],
                ],
                'message',
            ])
            ->assertJsonCount(1, 'data');

        $this->assertDatabaseCount('artist_images', 1);

        $image = ArtistImage::first();

        $this->assertSame($artist->id, $image->artist_profile_id);
        $this->assertFalse((bool) $image->is_main);
        $this->assertStringStartsWith('artists/', $image->image_url);

        Storage::disk('public')->assertExists($image->image_url);
    }

    public function test_store_rejects_when_user_is_not_the_artist(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $artist = ArtistProfile::factory()->for($owner)->create();

        Sanctum::actingAs($intruder);

        $payload = [
            'images' => [
                UploadedFile::fake()->image('image1.jpg'),
            ],
        ];

        $response = $this->postJson(route('artist.image.store', $artist->id), $payload);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Forbidden',
            ]);

        $this->assertDatabaseCount('artist_images', 0);
        $this->assertEmpty(Storage::disk('public')->files('artists'));
    }

    public function test_store_rejects_more_than_ten_images(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $artist = ArtistProfile::factory()->for($user)->create();

        $images = [];
        for ($i = 1; $i <= 11; $i++) {
            $images[] = UploadedFile::fake()->image("image{$i}.jpg");
        }

        $response = $this->postJson(route('artist.image.store', $artist->id), [
            'images' => $images,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images']);

        $this->assertDatabaseCount('artist_images', 0);
        $this->assertEmpty(Storage::disk('public')->files('artists'));
    }

    public function test_store_rejects_files_with_invalid_mime_type(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $artist = ArtistProfile::factory()->for($user)->create();

        $response = $this->postJson(route('artist.image.store', $artist->id), [
            'images' => [
                UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);

        $this->assertDatabaseCount('artist_images', 0);
        $this->assertEmpty(Storage::disk('public')->files('artists'));
    }

    public function test_store_rejects_files_with_spoofed_content_type(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $artist = ArtistProfile::factory()->for($user)->create();

        $response = $this->postJson(route('artist.image.store', $artist->id), [
            'images' => [
                UploadedFile::fake()->create('portfolio.jpg', 100, 'text/plain'),
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);

        $this->assertDatabaseCount('artist_images', 0);
        $this->assertEmpty(Storage::disk('public')->files('artists'));
    }

    public function test_store_rejects_images_exceeding_max_dimensions(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $artist = ArtistProfile::factory()->for($user)->create();

        $response = $this->postJson(route('artist.image.store', $artist->id), [
            'images' => [
                UploadedFile::fake()->image('oversized.jpg', 5000, 5000),
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);

        $this->assertDatabaseCount('artist_images', 0);
        $this->assertEmpty(Storage::disk('public')->files('artists'));
    }

    public function test_set_main_promotes_target_image_and_demotes_previous(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $artist = ArtistProfile::factory()->for($user)->create();

        $previousMain = ArtistImage::factory()->for($artist, 'artist')->main()->create();
        $target = ArtistImage::factory()->for($artist, 'artist')->create();

        $response = $this->patchJson(route('artist.image.set-main', $target->id));

        $response->assertOk()
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.is_main', true);

        $this->assertDatabaseHas('artist_images', [
            'id' => $target->id,
            'is_main' => true,
        ]);

        $this->assertDatabaseHas('artist_images', [
            'id' => $previousMain->id,
            'is_main' => false,
        ]);
    }

    public function test_set_main_requires_authentication(): void
    {
        $owner = User::factory()->create();
        $artist = ArtistProfile::factory()->for($owner)->create();

        $image = ArtistImage::factory()->for($artist, 'artist')->create();

        $response = $this->patchJson(route('artist.image.set-main', $image->id));

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated',
            ]);

        $this->assertDatabaseHas('artist_images', [
            'id' => $image->id,
            'is_main' => false,
        ]);
    }

    public function test_set_main_rejects_when_image_is_not_owned_by_the_artist(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $artist = ArtistProfile::factory()->for($owner)->create();

        $image = ArtistImage::factory()->for($artist, 'artist')->create();

        Sanctum::actingAs($intruder);

        $response = $this->patchJson(route('artist.image.set-main', $image->id));

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden');

        $this->assertDatabaseHas('artist_images', [
            'id' => $image->id,
            'is_main' => false,
        ]);
    }

    public function test_destroy_removes_non_main_image_and_its_file(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $artist = ArtistProfile::factory()->for($user)->create();

        $image = ArtistImage::factory()->for($artist, 'artist')->create();

        Storage::disk('public')->put($image->image_url, 'fake-content');

        $response = $this->deleteJson(route('artist.image.destroy', $image->id));

        $response->assertOk()
            ->assertJsonPath('message', 'Image deleted successfully');

        $this->assertDatabaseMissing('artist_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($image->image_url);
    }

    public function test_destroy_requires_authentication(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $artist = ArtistProfile::factory()->for($owner)->create();

        $image = ArtistImage::factory()->for($artist, 'artist')->create();

        Storage::disk('public')->put($image->image_url, 'fake-content');

        $response = $this->deleteJson(route('artist.image.destroy', $image->id));

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated',
            ]);

        $this->assertDatabaseHas('artist_images', ['id' => $image->id]);
        Storage::disk('public')->assertExists($image->image_url);
    }

    public function test_destroy_blocks_main_image_deletion(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $artist = ArtistProfile::factory()->for($user)->create();

        $image = ArtistImage::factory()->for($artist, 'artist')->main()->create();

        Storage::disk('public')->put($image->image_url, 'fake-content');

        $response = $this->deleteJson(route('artist.image.destroy', $image->id));

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Cannot delete main image');

        $this->assertDatabaseHas('artist_images', [
            'id' => $image->id,
            'is_main' => true,
        ]);
        Storage::disk('public')->assertExists($image->image_url);
    }

    public function test_destroy_forbids_non_owner_from_deleting_image(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $artist = ArtistProfile::factory()->for($owner)->create();

        $image = ArtistImage::factory()->for($artist, 'artist')->create();

        Storage::disk('public')->put($image->image_url, 'fake-content');

        Sanctum::actingAs($intruder);

        $response = $this->deleteJson(route('artist.image.destroy', $image->id));

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden');

        $this->assertDatabaseHas('artist_images', ['id' => $image->id]);
        Storage::disk('public')->assertExists($image->image_url);
    }

    public function test_destroy_allows_admin_to_delete_other_artist_image(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $artist = ArtistProfile::factory()->for($owner)->create();

        $image = ArtistImage::factory()->for($artist, 'artist')->create();

        Storage::disk('public')->put($image->image_url, 'fake-content');

        Sanctum::actingAs($admin);

        $response = $this->deleteJson(route('artist.image.destroy', $image->id));

        $response->assertOk()
            ->assertJsonPath('message', 'Image deleted successfully');

        $this->assertDatabaseMissing('artist_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($image->image_url);
    }

    public function test_set_main_forbids_admin_from_acting_on_other_artist_image(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $artist = ArtistProfile::factory()->for($owner)->create();

        $image = ArtistImage::factory()->for($artist, 'artist')->create();

        Sanctum::actingAs($admin);

        $response = $this->patchJson(route('artist.image.set-main', $image->id));

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden');

        $this->assertDatabaseHas('artist_images', [
            'id' => $image->id,
            'is_main' => false,
        ]);
    }
}
