<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImageRequest;
use App\Http\Resources\ArtistImageResource;
use App\Services\ArtistImageService;

class ArtistImageController extends Controller
{

    public function __construct(private ArtistImageService $artistImageService)
    {
        $this->artistImageService = $artistImageService;
    }

    public function store(StoreImageRequest $request, $id)
    {
        $images = $this->artistImageService->multipleUpload($id, $request->file('images', []));
        return ArtistImageResource::collection(collect($images));
    }
}
