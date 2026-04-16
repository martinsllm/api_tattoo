<?php

namespace App\Services;

use App\Models\ArtistProfile;
use App\Models\User;

class ArtistService
{
    public function create(array $data): ArtistProfile
    {
        // modo teste
        $user = User::factory()->create();

        if ($user->artistProfile) {
            throw new \Exception('User already has an artist profile');
        }

        return $user->artistProfile()->create($data);
    }
}
