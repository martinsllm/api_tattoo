<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\StyleResource;
use App\Models\Style;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class StyleController extends Controller
{
    public function index(): JsonResponse
    {
        $styles = Cache::remember('styles', 3600, fn () => StyleResource::collection(Style::all())->resolve());

        return ApiResponse::success($styles, 'Styles retrieved successfully');
    }
}
