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
        return ApiResponse::success([
            'total_artists' => ArtistProfile::count(),
            'total_reviews' => Review::count(),
            'total_favorites' => DB::table('favorites')->count(),
        ], 'Metrics retrieved successfully');
    }
}
