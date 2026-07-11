<?php

namespace Tests\Feature\Auth;

use App\Models\ArtistImage;
use App\Models\ArtistProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_delete_own_account_with_correct_password(): void
    {
        $user = User::factory()->create();
        $user->createToken('api-token');

        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('auth.delete'), [
            'current_password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Conta excluída com sucesso.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_deleting_artist_account_removes_profile_and_image_files(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $artist = ArtistProfile::factory()->for($user)->create();

        $images = ArtistImage::factory()->for($artist, 'artist')->count(3)->create();

        foreach ($images as $image) {
            Storage::disk('public')->put($image->image_url, 'fake-content');
        }

        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('auth.delete'), [
            'current_password' => 'password',
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('artist_profiles', ['id' => $artist->id]);
        $this->assertDatabaseCount('artist_images', 0);

        foreach ($images as $image) {
            Storage::disk('public')->assertMissing($image->image_url);
        }
    }

    public function test_delete_account_fails_with_wrong_password(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('auth.delete'), [
            'current_password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_delete_account_fails_when_password_is_missing(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson(route('auth.delete'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_delete_account_requires_authentication(): void
    {
        $response = $this->deleteJson(route('auth.delete'), [
            'current_password' => 'password',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);
    }

    public function test_deleting_account_anonymizes_authored_reviews(): void
    {
        $reviewer = User::factory()->create();
        $artistOwner = User::factory()->create();
        $artist = ArtistProfile::factory()->for($artistOwner)->create();

        $review = Review::factory()->create([
            'user_id' => $reviewer->id,
            'artist_profile_id' => $artist->id,
        ]);

        Sanctum::actingAs($reviewer);

        $response = $this->deleteJson(route('auth.delete'), [
            'current_password' => 'password',
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $reviewer->id]);
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => null,
        ]);
    }
}
