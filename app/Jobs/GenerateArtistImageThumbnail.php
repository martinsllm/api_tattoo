<?php

namespace App\Jobs;

use App\Models\ArtistImage;
use App\Services\ArtistImageThumbnailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateArtistImageThumbnail implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $artistImageId) {}

    /**
     * Execute the job.
     */
    public function handle(ArtistImageThumbnailService $artistImageThumbnailService): void
    {
        try {
            $image = ArtistImage::find($this->artistImageId);

            if ($image === null || $image->thumbnail_url) {
                return;
            }

            $artistImageThumbnailService->generate($image);
        } catch (\Exception $e) {
            Log::error('Error generating artist image thumbnail: '.$e->getMessage());

            return;
        }
    }
}
