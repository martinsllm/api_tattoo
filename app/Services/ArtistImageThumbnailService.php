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

        $thumbPath = 'artists/thumbs/'.$image->id.'.jpg';

        $thumbnail = Image::decodePath($originalPath)->cover(100, 100);
        $thumbnail->save($publicPath->path($thumbPath));
        $image->update(['thumbnail_url' => $thumbPath]);
    }
}
