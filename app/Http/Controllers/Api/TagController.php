<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TagController extends Controller
{
    public function index(): JsonResponse
    {
        $tags = Cache::remember('tags', 3600, fn () => TagResource::collection(Tag::all())->resolve());

        return ApiResponse::success($tags, 'Tags retrieved successfully');
    }
}
