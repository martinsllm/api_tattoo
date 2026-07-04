<?php

namespace Tests\Feature\Admin;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');
        Role::findOrCreate('artist');
        Role::findOrCreate('admin');
    }

    public function test_destroy_removes_review_when_called_by_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $review = Review::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->deleteJson(route('admin.review.destroy', $review->id));

        $response->assertOk()
            ->assertJson(['message' => 'Review deleted successfully']);

        $this->assertSoftDeleted('reviews', ['id' => $review->id]);
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_destroy_forbids_anonymous_user(): void
    {
        $review = Review::factory()->create();

        $response = $this->deleteJson(route('admin.review.destroy', $review->id));

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated']);

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    public function test_destroy_forbids_authenticated_client(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $review = Review::factory()->create();

        Sanctum::actingAs($client);

        $response = $this->deleteJson(route('admin.review.destroy', $review->id));

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    public function test_destroy_forbids_authenticated_artist(): void
    {
        $artistUser = User::factory()->create();
        $artistUser->assignRole('artist');

        $review = Review::factory()->create();

        Sanctum::actingAs($artistUser);

        $response = $this->deleteJson(route('admin.review.destroy', $review->id));

        $response->assertStatus(403)
            ->assertJson(['message' => 'Forbidden']);

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    public function test_destroy_returns_404_when_review_does_not_exist(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->deleteJson(route('admin.review.destroy', 9999));

        $response->assertStatus(404)
            ->assertJson(['message' => 'Resource not found']);
    }

    public function test_admin_group_has_throttle_middleware(): void
    {
        $middleware = Route::getRoutes()
            ->getByName('admin.review.destroy')
            ->gatherMiddleware();

        $this->assertContains('throttle:30,1', $middleware);
    }
}
