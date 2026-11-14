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
        $admin = User::factory()->admin()->create();

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

    public function test_index_returns_paginated_reports_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->createReportFor($user);
        $this->createReportFor($user);

        Sanctum::actingAs($user);

        $response = $this->getJson(route('report.index'));

        $response->assertOk()
            ->assertJsonPath('message', 'Reports fetched successfully')
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'total', 'per_page', 'last_page'],
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_index_lists_only_reports_from_authenticated_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->createReportFor($userA);
        $this->createReportFor($userA);
        $reportB = $this->createReportFor($userB);

        Sanctum::actingAs($userA);

        $response = $this->getJson(route('report.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($reportB->id));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson(route('report.index'));

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_index_includes_report_after_store(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson(route('report.store'), [
            'reportable_type' => 'review',
            'reportable_id' => $review->id,
            'reason' => 'spam',
        ])->assertCreated();

        $response = $this->getJson(route('report.index'));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reportable_id', $review->id)
            ->assertJsonPath('data.0.status', 'pending');
    }

    private function createReportFor(User $reporter, array $overrides = []): Report
    {
        $review = Review::factory()->create();

        return Report::create(array_merge([
            'reporter_id' => $reporter->id,
            'reportable_type' => Review::class,
            'reportable_id' => $review->id,
            'reason' => ReportReason::SPAM,
            'status' => ReportStatus::PENDING,
        ], $overrides));
    }
}
