<?php

namespace App\Services;

use App\Models\ArtistImage;

class ArtistImageService
{
    public function multipleUpload($artist, $files)
    {
        $images = [];

        foreach ($files as $file) {
            $image_url = $file->store('artists', 'public');

            $images[] = ArtistImage::create([
                'artist_profile_id' => $artist->id,
                'image_url' => $image_url,
            ]);
        }

        return $images;
    }

    public function setMain($image)
    {
        // remove outras como main
        $image->artist->images()->update(['is_main' => false]);

        // define essa como principal
        $image->update(['is_main' => true]);

        return $image;
    }

    public function delete($image)
    {
        if ($image->is_main) {
            throw new \DomainException('Cannot delete main image');
        }

        \Storage::disk('public')->delete($image->image_url);

        $image->delete();

        return true;
    }
}
