<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImageRequest;
use App\Http\Resources\ArtistImageResource;
use App\Models\ArtistImage;
use App\Models\ArtistProfile;
use App\Services\ArtistImageService;

class ArtistImageController extends Controller
{

    public function __construct(private ArtistImageService $artistImageService)
    {
        $this->artistImageService = $artistImageService;
    }

    public function store(StoreImageRequest $request, $id)
    {
        $artist = ArtistProfile::findOrFail($id);

        $this->authorize('update', $artist);

        $images = $this->artistImageService->multipleUpload($artist, $request->file('images', []));
        
        return ArtistImageResource::collection(collect($images));
    }

    public function setMain($id)
    {
        $image = ArtistImage::findOrFail($id);

        $this->authorize('update', $image);

        $image = $this->artistImageService->setMain($image);
        return new ArtistImageResource($image);
       
    }

    public function destroy($id)
    {
        $image = ArtistImage::findOrFail($id);

        $this->authorize('delete', $image);

        $this->artistImageService->delete($image);
        return response()->json(['message' => 'Image deleted successfully']);
    }
}
