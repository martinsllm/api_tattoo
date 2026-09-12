<?php

namespace App\Services;

use App\Models\ArtistImage;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ArtistImageThumbnailService
{
    public function generate(ArtistImage $image): void
    {
        $publicPath = Storage::disk('public');

        $originalPath = $publicPath->path($image->image_url);

        if (! file_exists($originalPath)) {
            return;
        }

        $publicPath->makeDirectory('artists/thumbs/');

        $thumbPath = $this->pathFor($image->id);

        $thumbnail = Image::decodePath($originalPath)->cover(100, 100);
        $thumbnail->save($publicPath->path($thumbPath));
        $image->update(['thumbnail_url' => $thumbPath]);
    }

    public function delete(ArtistImage $image): void
    {
        $paths = array_unique(array_filter([
            $image->thumbnail_url,
            $this->pathFor($image->id),
        ]));

        Storage::disk('public')->delete($paths);
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
