<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

    public function store(Request $request)
    {
        $user = User::factory()->create();

        if (!$user) {
            return response()->json([
                'message' => 'No users found. Create a user first.'
            ], 400);
        }

        // Validação
        $data = $request->validate([
            'studio_name' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'instagram' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        // Um perfil por usuário
        if ($user->artistProfile) {
            return response()->json([
                'message' => 'User already has an artist profile'
            ], 400);
        }

        // Criar perfil
        $artist = ArtistProfile::create([
            ...$data,
            'user_id' => $user->id,
        ]);

        return response()->json($artist, 201);
    }
}
