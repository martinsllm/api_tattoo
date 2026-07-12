<?php

namespace App\Console\Commands;

use App\Models\ArtistImage;
use App\Models\AuditLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('data:prune {--dry-run : Show what would be pruned without deleting anything}')]
#[Description('Prune orphaned files and expired audit logs')]
class DataPruneCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $orphanedImagesCount = $this->pruneOrphanedImages($dryRun);
        $expiredAuditLogsCount = $this->pruneExpiredAuditLogs($dryRun);

        $this->info("Orphaned images: {$orphanedImagesCount}");
        $this->info("Expired audit logs: {$expiredAuditLogsCount}");

        return self::SUCCESS;
    }

    private function pruneOrphanedImages(bool $dryRun): int
    {
        $disk = Storage::disk('public');

        $storedImages = ArtistImage::pluck('image_url')->all();

        $files = $disk->allFiles('artists');

        $orphanedFiles = collect($files)
            ->reject(fn (string $file): bool => in_array($file, $storedImages, true))
            ->values();

        if (! $dryRun) {
            $disk->delete($orphanedFiles->all());
        }

        return $orphanedFiles->count();
    }

    private function pruneExpiredAuditLogs(bool $dryRun): int
    {
        $cutoffDate = now()->subDays(180);

        $query = AuditLog::where('created_at', '<', $cutoffDate);

        $count = $query->count();

        if ($dryRun) {
            return $count;
        }

        $query->delete();

        return $count;
    }
}
