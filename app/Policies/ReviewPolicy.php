<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function delete(User $user, Review $review): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->id === $review->user_id;
    }
}
