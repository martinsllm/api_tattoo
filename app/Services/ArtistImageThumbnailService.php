<?php

namespace App\Services;

use App\Models\ArtistImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Intervention\Image\Laravel\Facades\Image;

class ArtistImageThumbnailService
{
    public function __construct(private readonly Filesystem $disk) {}

    public function generate(ArtistImage $image): void
    {
        $originalPath = $this->disk->path($image->image_url);

        if (! file_exists($originalPath)) {
            return;
        }

        $this->disk->makeDirectory('artists/thumbs/');

        $thumbPath = $this->pathFor($image->id);

        $thumbnail = Image::decodePath($originalPath)->cover(100, 100);
        $thumbnail->save($this->disk->path($thumbPath));
        $image->update(['thumbnail_url' => $thumbPath]);
    }

    public function delete(ArtistImage $image): void
    {
        $paths = array_unique(array_filter([
            $image->thumbnail_url,
            $this->pathFor($image->id),
        ]));

        $this->disk->delete($paths);
    }

    public function pathsInUse(): array
    {
        return ArtistImage::query()
            ->whereNotNull('thumbnail_url')
            ->pluck('thumbnail_url')
            ->all();
    }

    private function pathFor(int $artistImageId): string
    {
        return 'artists/thumbs/'.$artistImageId.'.jpg';
    }
}
