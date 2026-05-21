<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArtistResource;
use App\Services\FavoriteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FavoriteController extends Controller
{
    public function __construct(private FavoriteService $favoriteService) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:50']]);

        $favorites = $user->favorites()
            ->where('artist_profiles.is_active', true)
            ->with(['user', 'styles', 'tags', 'images'])
            ->withAvg('reviews', 'rating')
            ->paginate($request->integer('per_page', 10));

        return ApiResponse::paginate(ArtistResource::collection($favorites), 'Favorite artists retrieved successfully');
    }

    public function toggle($artistId)
    {
        if (Auth::user()->hasRole('admin')) {
            throw new AccessDeniedHttpException;
        }

        $favorited = $this->favoriteService->toggle($artistId);

        $message = $favorited ? 'Added to favorites' : 'Removed from favorites';

        return ApiResponse::success(null, $message);
    }
}
