<?php

namespace App\Policies;

use App\Models\ArtistProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ArtistProfilePolicy
{
    public function create(User $user): Response|bool
    {
        if ($user->hasVerifiedEmail()) {
            return true;
        }

        return Response::deny('Verifique seu e-mail antes de criar um perfil de artista.');
    }

    public function update(User $user, ArtistProfile $artist): bool
    {
        return $user->id === $artist->user_id;
    }
}
