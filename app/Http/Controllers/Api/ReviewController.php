<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\ArtistProfile;
use App\Models\Review;
use App\Services\ReviewService;

class ReviewController extends Controller
{
    public function __construct(private ReviewService $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    public function index($artistId)
    {
        $artist = ArtistProfile::find($artistId);

        if(!$artist){
            return response()->json(['error' => 'Artist not found'], 404);
        }

        $reviews = Review::where('artist_profile_id', $artistId)
            ->with('user')
            ->latest()
            ->paginate(10);

        return ReviewResource::collection($reviews);
    }

    public function store(StoreReviewRequest $request)
    {
        try {
            $review = $this->reviewService->create($request->validated());
            return response()->json($review, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
