<?php

namespace App\Services;
use App\Models\ArtistProfile;
use Illuminate\Support\Facades\Auth;

class FavoriteService
{
    public function toggle($artistId) {
        $user = Auth::user();

        $artist = ArtistProfile::findOrFail($artistId);

        // impedir autofavorito
        if ($artist->user_id === $user->id) {
            throw new \Exception('You cannot favorite yourself');
        }

        if ($user->favorites()->where('artist_profile_id', $artistId)->exists()) {
            $user->favorites()->detach($artistId);
            return ['message' => 'Removed from favorites'];
        }

        $user->favorites()->attach($artistId);

        return ['message' => 'Added to favorites'];
            
    }
}