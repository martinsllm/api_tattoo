<?php

namespace App\Policies;

use App\Models\ArtistImage;
use App\Models\User;

class ArtistImagePolicy
{
    public function update(User $user, ArtistImage $image): bool
    {
        return $user->id === $image->artist->user_id;
    }

    public function delete(User $user, ArtistImage $image): bool
    {
        return $user->id === $image->artist->user_id;
    }
}
