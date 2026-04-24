<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArtistResource;
use App\Services\FavoriteService;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function __construct(private FavoriteService $favoriteService)
    {
        $this->favoriteService = $favoriteService;
    }

    public function index() {
        $user = Auth::user();

        $favorites = $user->favorites()
            ->with(['styles', 'tags', 'images'])
            ->withAvg('reviews', 'rating')
            ->paginate(10);
        
        return ApiResponse::success(ArtistResource::collection($favorites));
    }

    public function toggle($artistId) {
        return ApiResponse::success($this->favoriteService->toggle($artistId));
    }
}
