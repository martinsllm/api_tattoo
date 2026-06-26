<?php

namespace App\Services;

use App\Traits\ResolvesActiveArtist;
use Illuminate\Support\Facades\Auth;

class FavoriteService
{
    use ResolvesActiveArtist;

    public function toggle(int $artistId): bool
    {
        $user = Auth::user();

        // impedir autofavorito
        $this->resolveActiveArtistForAction($artistId, 'You cannot favorite yourself.');

        if ($user->favorites()->where('artist_profile_id', $artistId)->exists()) {
            $user->favorites()->detach($artistId);

            return false;
        }

        $user->favorites()->attach($artistId);

        return true;

    }
}
