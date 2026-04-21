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
        unset($data['styles']);

        if ($user->artistProfile) {
            throw new \Exception('User already has an artist profile');
        }

        $artist = $user->artistProfile()->create($data);

        if (!empty($styles)) {
            $artist->styles()->sync($styles);
        }

        return $artist;
    }

    public function update($artistId, array $data): ArtistProfile
    {
        $user = Auth::user();

        $artist = ArtistProfile::findOrFail($artistId);

        // Verifica se o usuário é o dono do perfil
        if ($artist->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }

        $styles = $data['styles'] ?? null;
        unset($data['styles']);

        $artist->update($data);

        if (!is_null($styles)) {
            $artist->styles()->sync($styles);
        }

        return $artist;
    }
}
