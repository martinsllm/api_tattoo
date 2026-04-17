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
}
