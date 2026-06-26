<?php

namespace App\Services;

use App\Models\Review;
use App\Traits\ResolvesActiveArtist;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;

class ReviewService
{
    use ResolvesActiveArtist;

    public function create(array $data): Review
    {
        $user = Auth::user();

        // impedir autoavaliação
        $artist = $this->resolveActiveArtistForAction($data['artist_profile_id'], 'You cannot review yourself.');

        $existingReview = $artist->reviews()->withTrashed()->where('user_id', $user->id)->first();

        if ($existingReview) {
            if ($existingReview->trashed()) {
                $existingReview->restore();
                $existingReview->update([
                    'rating' => $data['rating'],
                    'comment' => $data['comment'] ?? null,
                ]);

                return $existingReview->fresh();
            }

            throw new \DomainException('You have already reviewed this artist.');
        }

        try {
            return Review::create([
                'user_id' => $user->id,
                'artist_profile_id' => $data['artist_profile_id'],
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            throw new \DomainException('You have already reviewed this artist.');
        }
    }
}
