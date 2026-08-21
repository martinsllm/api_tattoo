<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ArtistProfile;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): JsonResponse
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        return ApiResponse::success([
            'total_artists' => ArtistProfile::count(),
            'total_reviews' => Review::count(),
            'total_favorites' => DB::table('favorites')->count(),
            'active_artists' => ArtistProfile::where('is_active', true)->count(),
            'inactive_artists' => ArtistProfile::where('is_active', false)->count(),
            'reviews_this_month' => Review::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
            'favorites_this_month' => DB::table('favorites')->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
        ], 'Metrics retrieved successfully');
    }
}
