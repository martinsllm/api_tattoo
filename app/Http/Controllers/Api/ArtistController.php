<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterArtistsRequest;
use App\Http\Requests\StoreArtistRequest;
use App\Http\Requests\UpdateArtistRequest;
use App\Http\Resources\ArtistResource;
use App\Models\ArtistProfile;
use App\Services\ArtistService;

class ArtistController extends Controller
{
    public function __construct(private ArtistService $artistService) {}

    public function index(FilterArtistsRequest $request)
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radius = $request->input('radius', 10);

        $styles = $request->input('styles');
        $tags = $request->input('tags');
        $city = $request->input('city');
        $state = $request->input('state');
        $studioName = $request->input('q');

        $query = ArtistProfile::with([
            'user',
            'styles',
            'tags',
            'images',
        ])
            ->active();

        // média de avaliação
        $query->withAvg('reviews', 'rating');
        $query->withCount('reviews');
        $query->withCount(['favoritedBy as favorites_count']);

        $hasGeo = ! is_null($lat) && ! is_null($lng);

        // Geolocalização
        if ($hasGeo) {
            $query->withDistance($lat, $lng)
                ->withinRadius($radius);
        } else {
            $query->orderByRaw('COALESCE(reviews_avg_rating, 0) DESC')
                ->orderByDesc('reviews_count');
        }

        // Filtro por styles
        if ($request->filled('styles')) {
            $query->filterStyles($styles);
        }

        // Filtro por tags
        if ($request->filled('tags')) {
            $query->filterTags($tags);
        }

        // Filtro por cidade
        if ($request->filled('city')) {
            $query->filterCity($city);
        }

        // Filtro por estado
        if ($request->filled('state')) {
            $query->filterState($state);
        }

        // Filtro por nome do estúdio
        if ($request->filled('q')) {
            $query->filterStudioName($studioName);
        }

        return ApiResponse::paginate(ArtistResource::collection($query->paginate($request->integer('per_page', 10))), 'Artists retrieved successfully');
    }

    public function show($id)
    {
        $artist = ArtistProfile::with([
            'user',
            'styles',
            'tags',
            'images',
        ])
            ->active()
            ->withCount(['favoritedBy as favorites_count'])
            ->findOrFail($id);

        return ApiResponse::success(new ArtistResource($artist), 'Artist retrieved successfully');
    }

    public function store(StoreArtistRequest $request)
    {
        $artist = $this->artistService->create($request->validated());

        return ApiResponse::success(new ArtistResource($artist), 'Artist created successfully');
    }

    public function update(UpdateArtistRequest $request, $id)
    {
        $artist = ArtistProfile::findOrFail($id);

        $this->authorize('update', $artist);

        $artist = $this->artistService->update($artist, $request->validated());

        return ApiResponse::success(new ArtistResource($artist), 'Artist updated successfully');
    }

    public function deactivate($id)
    {
        $artist = ArtistProfile::findOrFail($id);

        $this->authorize('update', $artist);

        $this->artistService->deactivate($artist);

        return ApiResponse::success(null, 'Artist deactivated successfully');
    }

    public function activate($id)
    {
        $artist = ArtistProfile::findOrFail($id);

        $this->authorize('update', $artist);

        $this->artistService->activate($artist);

        return ApiResponse::success(null, 'Artist activated successfully');
    }
}
