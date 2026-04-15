<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreArtistRequest;
use App\Models\ArtistProfile;
use App\Models\User;
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
            $query->withDistance($lat, $lng)
              ->withinRadius($radius);
        }

        // Filtro por styles
        if (!empty($styles)) {
            $query->filterStyles($styles);
        }

        // Filtro por tags
        if (!empty($tags)) {
            $query->filterTags($tags);
        }

        // Ordenação por rating (se não tiver geo)
        if (is_null($lat) || is_null($lng)) {
            $query->orderByDesc('reviews_avg_rating');
        }

        return response()->json(
            $query->paginate(10)
        );
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

        return response()->json($artist);
    }

    public function store(StoreArtistRequest $request)
    {
        $user = User::factory()->create();

        if (!$user) {
            return response()->json([
                'message' => 'No users found. Create a user first.'
            ], 400);
        }

        // Um perfil por usuário
        if ($user->artistProfile) {
            return response()->json([
                'message' => 'User already has an artist profile'
            ], 400);
        }

        // Criar perfil
        $artist = $user->artistProfile()->create(
            $request->validated()
        );

        return response()->json($artist, 201);
    }
}
