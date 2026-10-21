<?php

namespace App\Http\Controllers\Api;

use App\Actions\ToggleArtistFavoriteAction;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\PaginatedRequest;
use App\Http\Resources\ArtistResource;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FavoriteController extends Controller
{
    public function index(PaginatedRequest $request)
    {
        $user = Auth::user();

        $favorites = $user->favorites()
            ->where('artist_profiles.is_active', true)
            ->with(['user', 'styles', 'tags', 'images'])
            ->withAvg('reviews', 'rating')
            ->paginate($request->integer('per_page', 10));

        return ApiResponse::paginate(ArtistResource::collection($favorites), 'Favorite artists retrieved successfully');
    }

    public function toggle(int $artistId, ToggleArtistFavoriteAction $toggle)
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) {
            throw new AccessDeniedHttpException;
        }

        $favorited = $toggle($user, $artistId);

        $message = $favorited ? 'Added to favorites' : 'Removed from favorites';

        return ApiResponse::success(null, $message);
    }
}
