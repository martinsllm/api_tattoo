<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImageRequest;
use App\Http\Resources\ArtistImageResource;
use App\Models\ArtistImage;
use App\Models\ArtistProfile;
use App\Services\ArtistImageService;

class ArtistImageController extends Controller
{
    public function __construct(private ArtistImageService $artistImageService) {}

    public function store(StoreImageRequest $request, ArtistProfile $artist)
    {
        $this->authorize('update', $artist);

        $images = $this->artistImageService->multipleUpload($artist, $request->file('images', []));

        return ApiResponse::success(ArtistImageResource::collection($images), 'Images uploaded successfully', 201);
    }

    public function setMain(ArtistImage $image)
    {
        $this->authorize('update', $image);

        $image = $this->artistImageService->setMain($image);

        return ApiResponse::success(new ArtistImageResource($image), 'Main image set successfully');

    }

    public function destroy(ArtistImage $image)
    {
        $this->authorize('delete', $image);

        $this->artistImageService->delete($image);

        return ApiResponse::success(null, 'Image deleted successfully');
    }
}
