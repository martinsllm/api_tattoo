<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArtistProfile;
use Illuminate\Http\Request;

class ArtistController extends Controller
{
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
        ->where('is_active', true);

        // média de avaliação
        $query->withAvg('reviews', 'rating');

        // Geolocalização
        if (!is_null($lat) && !is_null($lng)) {
            $query->select('artist_profiles.*')->selectRaw("
                (6371 * acos(
                    cos(radians(?)) 
                    * cos(radians(latitude)) 
                    * cos(radians(longitude) - radians(?)) 
                    + sin(radians(?)) 
                    * sin(radians(latitude))
                )) AS distance
            ", [$lat, $lng, $lat]);

            $query->having('distance', '<=', $radius);
            $query->orderBy('distance');
        }

        // Filtro por styles
        if (!empty($styles)) {
            $query->whereHas('styles', function ($q) use ($styles) {
                $q->whereIn('styles.id', $styles);
            });
        }

        // Filtro por tags
        if (!empty($tags)) {
            $query->whereHas('tags', function ($q) use ($tags) {
                $q->whereIn('tags.id', $tags);
            });
        }

        // Ordenação por rating (se não tiver geo)
        if (is_null($lat) || is_null($lng)) {
            $query->orderByDesc('reviews_avg_rating');
        }

        // Paginação
        $artists = $query->paginate(10);

        return response()->json($artists);
    }
}
