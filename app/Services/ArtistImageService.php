<?php

namespace App\Services;

use App\Models\ArtistImage;
use App\Models\ArtistProfile;

class ArtistImageService
{
    public function multipleUpload($artistId, $files)
    {
        $artist = ArtistProfile::findOrFail($artistId);

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
}
