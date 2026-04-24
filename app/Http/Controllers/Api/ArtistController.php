<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreArtistRequest;
use App\Http\Requests\UpdateArtistRequest;
use App\Http\Resources\ArtistResource;
use App\Models\ArtistProfile;
use App\Services\ArtistService;
use Illuminate\Http\Request;

class ArtistController extends Controller
{

    public function __construct(private ArtistService $artistService)
    {
        $this->artistService = $artistService;
    }

    public function index(Request $request)
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radius = $request->input('radius',10);

        $styles = $request->input('styles'); // array
        $tags = $request->input('tags');     // array

        $query = ArtistProfile::with([
            'user',
            'styles',
            'tags',
            'images'
        ])
        ->active();

        // média de avaliação
        $query->withAvg('reviews', 'rating');
        $query->withCount('reviews');

        // Geolocalização
        if (!is_null($lat) && !is_null($lng)) {
            $query->withDistance($lat, $lng)
              ->withinRadius($radius);
        }

        // Filtro por styles
        if ($request->filled('styles')) {
            $query->filterStyles($styles);
        }

        // Filtro por tags
        if ($request->filled('tags')) {
            $query->filterTags($tags);
        }

        // Ordenação por rating (se não tiver geo)
        if (!$lat || !$lng) {
            $query->orderByRaw('COALESCE(reviews_avg_rating, 0) DESC')
                ->orderByDesc('reviews_count');
        }

        return ApiResponse::success(ArtistResource::collection($query->paginate(10)));
    }

    public function show($id)
    {
        $artist = ArtistProfile::with([
            'user',
            'styles',
            'tags',
            'images',
            'reviews.user'
        ])
        ->where('is_active', true)
        ->findOrFail($id);  

        return ApiResponse::success(new ArtistResource($artist));
    }

    public function store(StoreArtistRequest $request)
    {
        $artist = $this->artistService->create($request->validated());
        
        return ApiResponse::success(new ArtistResource($artist));
    }

    public function update(UpdateArtistRequest $request, $id)
    {
        $artist = ArtistProfile::findOrFail($id);

        $this->authorize('update', $artist);

        $artist = $this->artistService->update($artist, $request->validated());

        return ApiResponse::success(new ArtistResource($artist));
    }
}
