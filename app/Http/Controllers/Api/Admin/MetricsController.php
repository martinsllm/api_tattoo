<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ArtistProfile;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        $cacheKey = 'metrics_'.now()->format('Y_m');

        $cachedResults = Cache::remember($cacheKey, 60, function () {
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            return [
                'total_artists' => ArtistProfile::count(),
                'total_reviews' => Review::count(),
                'total_favorites' => DB::table('favorites')->count(),
                'active_artists' => ArtistProfile::where('is_active', true)->count(),
                'inactive_artists' => ArtistProfile::where('is_active', false)->count(),
                'reviews_this_month' => Review::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
                'favorites_this_month' => DB::table('favorites')->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
            ];
        });

        return ApiResponse::success($cachedResults, 'Metrics retrieved successfully');
    }
}
