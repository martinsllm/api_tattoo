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

    public function update(User $user, Review $review): bool
    {
        if ($user->hasRole('admin')) {
            return false;
        }

        return $user->id === $review->user_id && ! $review->trashed() && $review->created_at->greaterThan(now()->subHours(config('app.review_edit_window_hours')));
    }

    public function reply(User $user, Review $review): bool
    {
        if ($user->hasRole('admin')) {
            return false;
        }

        return $user->id === $review->artist->user_id && ! $review->trashed();
    }
}
