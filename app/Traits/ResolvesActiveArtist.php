<?php

namespace App\Traits;

use App\Models\ArtistProfile;
use Illuminate\Support\Facades\Auth;

trait ResolvesActiveArtist
{
    protected function resolveActiveArtistForAction(int $artistId, string $selfActionMessage): ArtistProfile
    {
        $artist = ArtistProfile::active()->findOrFail($artistId);

        if ($artist->user_id === Auth::id()) {
            throw new \DomainException($selfActionMessage);
        }

        return $artist;
    }
}
