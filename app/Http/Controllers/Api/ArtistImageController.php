<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImageRequest;
use App\Http\Resources\ArtistImageResource;
use App\Models\ArtistImage;
use App\Models\ArtistProfile;

class ArtistImageController extends Controller
{
    public function store(StoreImageRequest $request, $id)
    {
        $artist = ArtistProfile::findOrFail($id);

        $uploadedImages = [];

        foreach ($request->file('images') as $file) {
            $image_url = $file->store('artists', 'public');

            $image = ArtistImage::create([
                'artist_profile_id' => $artist->id,
                'image_url' => $image_url,
            ]);

            $uploadedImages[] = $image;
        }

        return ArtistImageResource::collection(collect($uploadedImages));
    }
}
