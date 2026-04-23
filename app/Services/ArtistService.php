<?php

namespace App\Services;

use App\Models\ArtistProfile;
use Illuminate\Support\Facades\Auth;

class ArtistService
{
    public function create(array $data): ArtistProfile
    {
        $user = Auth::user();

        $styles = $data['styles'] ?? [];
        $tags = $data['tags'] ?? [];
        unset($data['styles'], $data['tags']);

        if ($user->artistProfile) {
            throw new \Exception('User already has an artist profile');
        }

        $artist = $user->artistProfile()->create($data);

        if (!empty($styles)) {
            $artist->styles()->sync($styles);
        }

        if (!empty($tags)) {
            $artist->tags()->sync($tags);
        }

        return $artist;
    }

    public function update($artistId, array $data): ArtistProfile
    {
        $artist = ArtistProfile::findOrFail($artistId);

        $styles = $data['styles'] ?? null;
        $tags = $data['tags'] ?? null;
        unset($data['styles'], $data['tags']);

        $artist->update($data);

        if (!is_null($styles)) {
            $artist->styles()->sync($styles);
        }

        if (!is_null($tags)) {
            $artist->tags()->sync($tags);
        }

        return $artist;
    }
}
