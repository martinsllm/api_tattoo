<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\ArtistProfile;
use App\Models\Review;
use App\Services\ReviewService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    public function index($artistId)
    {
        ArtistProfile::active()->findOrFail($artistId);

        $reviews = Review::where('artist_profile_id', $artistId)
            ->with('user')
            ->latest()
            ->paginate(10);

        return ApiResponse::success(ReviewResource::collection($reviews), 'Reviews retrieved successfully');
    }

    public function store(StoreReviewRequest $request)
    {
        if ($request->user()->hasRole('admin')) {
            throw new AccessDeniedHttpException;
        }

        $review = $this->reviewService->create($request->validated());

        return ApiResponse::success(new ReviewResource($review), 'Review created successfully', 201);
    }
}
