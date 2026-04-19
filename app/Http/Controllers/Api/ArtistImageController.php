<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImageRequest;
use App\Http\Resources\ArtistImageResource;
use App\Services\ArtistImageService;
use Illuminate\Support\Facades\Auth;

class ArtistImageController extends Controller
{

    public function __construct(private ArtistImageService $artistImageService)
    {
        $this->artistImageService = $artistImageService;
    }

    public function store(StoreImageRequest $request, $id)
    {
        try {
            $images = $this->artistImageService->multipleUpload($id, $request->file('images', []));
            return ArtistImageResource::collection(collect($images));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    public function destroy($id)
    {
        try {
            $this->artistImageService->delete($id, Auth::user());
            return response()->json(['message' => 'Image deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }
}
