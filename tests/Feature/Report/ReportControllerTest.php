<?php

namespace Tests\Feature\Report;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\ArtistProfile;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
    }

    public function test_store_creates_report_for_review(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(route('report.store'), [
            'reportable_type' => 'review',
            'reportable_id' => $review->id,
            'reason' => 'spam',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Report created successfully')
            ->assertJsonPath('data.reportable_id', $review->id)
            ->assertJsonPath('data.reportable_type', Review::class)
            ->assertJsonPath('data.reason', 'spam')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $user->id,
            'reportable_type' => Review::class,
            'reportable_id' => $review->id,
            'reason' => 'spam',
            'status' => 'pending',
        ]);
    }

    public function test_store_creates_report_for_artist_profile(): void
    {
        $user = User::factory()->create();
        $artist = ArtistProfile::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(route('report.store'), [
            'reportable_type' => 'artist_profile',
            'reportable_id' => $artist->id,
            'reason' => 'harassment',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.reportable_id', $artist->id)
            ->assertJsonPath('data.reportable_type', ArtistProfile::class)
            ->assertJsonPath('data.reason', 'harassment')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $user->id,
            'reportable_type' => ArtistProfile::class,
            'reportable_id' => $artist->id,
            'reason' => 'harassment',
            'status' => 'pending',
        ]);
    }

    public function test_store_normalizes_reason_casing_before_validation(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(route('report.store'), [
            'reportable_type' => 'review',
            'reportable_id' => $review->id,
            'reason' => 'Spam',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.reason', 'spam');

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $user->id,
            'reportable_id' => $review->id,
            'reason' => 'spam',
        ]);
    }

    public function test_store_requires_authentication(): void
    {
        $review = Review::factory()->create();

        $response = $this->postJson(route('report.store'), [
            'reportable_type' => 'review',
            'reportable_id' => $review->id,
            'reason' => 'spam',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated');

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_store_forbids_admin_from_creating_report(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $review = Review::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson(route('report.store'), [
            'reportable_type' => 'review',
            'reportable_id' => $review->id,
            'reason' => 'spam',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_store_rejects_invalid_reportable_type(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(route('report.store'), [
            'reportable_type' => 'user',
            'reportable_id' => $review->id,
            'reason' => 'spam',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reportable_type']);

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_store_rejects_nonexistent_reportable_id_for_review(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(route('report.store'), [
            'reportable_type' => 'review',
            'reportable_id' => 99999,
            'reason' => 'spam',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reportable_id']);

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_store_rejects_invalid_reason(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson(route('report.store'), [
            'reportable_type' => 'review',
            'reportable_id' => $review->id,
            'reason' => 'not-a-valid-reason',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_store_rejects_duplicate_pending_report_from_same_user(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        Report::create([
            'reporter_id' => $user->id,
            'reportable_type' => Review::class,
            'reportable_id' => $review->id,
            'reason' => ReportReason::SPAM,
            'status' => ReportStatus::PENDING,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson(route('report.store'), [
            'reportable_type' => 'review',
            'reportable_id' => $review->id,
            'reason' => 'harassment',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reportable_id']);

        $this->assertDatabaseCount('reports', 1);
    }
}
