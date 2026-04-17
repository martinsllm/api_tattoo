<?php

namespace App\Services;

use App\Models\ArtistProfile;
use Illuminate\Support\Facades\Auth;

class ArtistService
{
    public function create(array $data): ArtistProfile
    {
        $user = Auth::user();

        if ($user->artistProfile) {
            throw new \Exception('User already has an artist profile');
        }

        return $user->artistProfile()->create($data);
    }

    public function update($artistId, $user, array $data): ArtistProfile
    {
        $artist = ArtistProfile::findOrFail($artistId);

        // Verifica se o usuário é o dono do perfil
        if ($artist->user_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }

        $artist->update($data);
        
        return $artist;
    }
}
