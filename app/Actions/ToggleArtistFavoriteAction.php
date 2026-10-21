<?php

namespace App\Actions;

use App\Models\User;
use App\Traits\ResolvesActiveArtist;

class ToggleArtistFavoriteAction
{
    use ResolvesActiveArtist;

    public function __invoke(User $user, int $artistProfileId): bool
    {
        // impedir autofavorito
        $this->resolveActiveArtistForAction($artistProfileId, 'You cannot favorite yourself.');

        if ($user->favorites()->where('artist_profile_id', $artistProfileId)->exists()) {
            $user->favorites()->detach($artistProfileId);

            return false;
        }

        $user->favorites()->attach($artistProfileId);

        return true;
    }
}
