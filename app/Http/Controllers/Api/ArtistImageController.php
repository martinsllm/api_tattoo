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

        // salvar arquivo
        $image_url = $request->file('image')->store('artists', 'public');

        // salvar no banco
        $image = ArtistImage::create([
            'artist_profile_id' => $artist->id,
            'image_url' => $image_url,
        ]);

        return new ArtistImageResource($image);
    }
}
