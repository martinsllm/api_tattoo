<?php

namespace App\Policies;

use App\Models\ArtistProfile;
use App\Models\User;

class ArtistProfilePolicy
{
    public function update(User $user, ArtistProfile $artist): bool
    {
        return $user->id === $artist->user_id;
    }
}
