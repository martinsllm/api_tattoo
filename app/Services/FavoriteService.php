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
            throw new \DomainException('You cannot favorite yourself');
        }

        if ($user->favorites()->where('artist_profile_id', $artistId)->exists()) {
            $user->favorites()->detach($artistId);
            return false;
        }

        $user->favorites()->attach($artistId);

        return true;
            
    }
}