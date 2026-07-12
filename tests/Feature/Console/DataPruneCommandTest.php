<?php

namespace Tests\Feature\Console;

use App\Models\ArtistImage;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataPruneCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_orphaned_images_and_expired_audit_logs(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('artists/valid.jpg', 'valid-image');
        Storage::disk('public')->put('artists/orphaned.jpg', 'orphaned-image');

        ArtistImage::factory()->create([
            'image_url' => 'artists/valid.jpg',
        ]);

        $expiredAuditLog = $this->createAuditLog(now()->subDays(181));
        $recentAuditLog = $this->createAuditLog(now()->subDays(179));

        $this->artisan('data:prune')
            ->expectsOutput('Orphaned images: 1')
            ->expectsOutput('Expired audit logs: 1')
            ->assertSuccessful();

        Storage::disk('public')->assertExists('artists/valid.jpg');
        Storage::disk('public')->assertMissing('artists/orphaned.jpg');

        $this->assertModelMissing($expiredAuditLog);
        $this->assertModelExists($recentAuditLog);
    }

    public function test_dry_run_counts_prunable_data_without_deleting_it(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('artists/valid.jpg', 'valid-image');
        Storage::disk('public')->put('artists/orphaned.jpg', 'orphaned-image');

        ArtistImage::factory()->create([
            'image_url' => 'artists/valid.jpg',
        ]);

        $expiredAuditLog = $this->createAuditLog(now()->subDays(181));

        $this->artisan('data:prune', ['--dry-run' => true])
            ->expectsOutput('Orphaned images: 1')
            ->expectsOutput('Expired audit logs: 1')
            ->assertSuccessful();

        Storage::disk('public')->assertExists('artists/valid.jpg');
        Storage::disk('public')->assertExists('artists/orphaned.jpg');

        $this->assertModelExists($expiredAuditLog);
    }

    private function createAuditLog(\DateTimeInterface $createdAt): AuditLog
    {
        $user = User::factory()->create();

        return AuditLog::forceCreate([
            'actor_user_id' => $user->id,
            'action' => 'artist.activated',
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->id,
            'created_at' => $createdAt,
        ]);
    }
}
