<?php

namespace App\Jobs;

use App\Models\ArtistImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

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
    public function handle(): void
    {
        try {
            $image = ArtistImage::find($this->artistImageId);
            $publicPath = Storage::disk('public');

            if ($image === null) {
                return;
            }

            if ($image->thumbnail_url) {
                return;
            }

            $originalPath = $publicPath->path($image->image_url);

            if (! file_exists($originalPath)) {
                return;
            }

            $publicPath->makeDirectory('artists/thumbs/');

            $thumbPath = 'artists/thumbs/'.$image->id.'.jpg';

            $thumbnail = Image::decodePath($originalPath)->cover(100, 100);
            $thumbnail->save($publicPath->path($thumbPath));
            $image->update(['thumbnail_url' => $thumbPath]);
        } catch (\Exception $e) {
            Log::error('Error generating artist image thumbnail: '.$e->getMessage());

            return;
        }
    }
}
