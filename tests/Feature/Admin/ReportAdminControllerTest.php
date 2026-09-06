<?php

namespace Tests\Feature\Admin;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('client');
        Role::findOrCreate('admin');
    }

    public function test_index_returns_paginated_reports_for_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->createReport();
        $this->createReport();

        Sanctum::actingAs($admin);

        $response = $this->getJson(route('admin.report.index'));

        $response->assertOk()
            ->assertJsonPath('message', 'Reports retrieved successfully')
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'total', 'per_page', 'last_page'],
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $pendingReport = $this->createReport(['status' => ReportStatus::PENDING]);
        $this->createReport(['status' => ReportStatus::RESOLVED]);

        Sanctum::actingAs($admin);

        $response = $this->getJson(route('admin.report.index', ['status' => 'pending']));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pendingReport->id)
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_index_forbids_anonymous_user(): void
    {
        $this->createReport();

        $response = $this->getJson(route('admin.report.index'));

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_index_forbids_authenticated_client(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        Sanctum::actingAs($client);

        $response = $this->getJson(route('admin.report.index'));

        $response->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');
    }

    public function test_update_resolves_pending_report_for_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $report = $this->createReport();

        Sanctum::actingAs($admin);

        $response = $this->patchJson(route('admin.report.update', $report->id), [
            'status' => 'resolved',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Report updated successfully')
            ->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'resolved',
        ]);
    }

    public function test_update_dismisses_pending_report_for_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $report = $this->createReport();

        Sanctum::actingAs($admin);

        $response = $this->patchJson(route('admin.report.update', $report->id), [
            'status' => 'dismissed',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'dismissed');

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'dismissed',
        ]);
    }

    public function test_update_records_audit_log_on_resolve(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $report = $this->createReport();

        Sanctum::actingAs($admin);

        $this->patchJson(route('admin.report.update', $report->id), [
            'status' => 'resolved',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'report.resolve',
            'auditable_type' => Report::class,
            'auditable_id' => $report->id,
        ]);
    }

    public function test_update_records_audit_log_on_dismiss(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $report = $this->createReport();

        Sanctum::actingAs($admin);

        $this->patchJson(route('admin.report.update', $report->id), [
            'status' => 'dismissed',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'report.dismiss',
            'auditable_type' => Report::class,
            'auditable_id' => $report->id,
        ]);
    }

    public function test_update_rejects_already_processed_report(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $report = $this->createReport(['status' => ReportStatus::RESOLVED]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson(route('admin.report.update', $report->id), [
            'status' => 'dismissed',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Report already processed');

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'resolved',
        ]);
    }

    public function test_update_rejects_invalid_status_in_body(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $report = $this->createReport();

        Sanctum::actingAs($admin);

        $response = $this->patchJson(route('admin.report.update', $report->id), [
            'status' => 'pending',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'pending',
        ]);
    }

    public function test_update_forbids_authenticated_client(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $report = $this->createReport();

        Sanctum::actingAs($client);

        $response = $this->patchJson(route('admin.report.update', $report->id), [
            'status' => 'resolved',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Forbidden');

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'pending',
        ]);
    }

    public function test_update_returns_404_when_report_does_not_exist(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Sanctum::actingAs($admin);

        $response = $this->patchJson(route('admin.report.update', 9999), [
            'status' => 'resolved',
        ]);

        $response->assertNotFound()
            ->assertJsonPath('message', 'Resource not found');
    }

    private function createReport(array $overrides = []): Report
    {
        $reporter = User::factory()->create();
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
