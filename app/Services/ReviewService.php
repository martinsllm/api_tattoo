<?php

namespace App\Services;

use App\Models\ArtistProfile;
use App\Models\Review;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;

class ReviewService
{
    public function create(array $data): Review
    {
        $user = Auth::user();

        // impedir autoavaliação
        $artist = ArtistProfile::active()->findOrFail($data['artist_profile_id']);

        if ($artist->user_id === $user->id) {
            throw new \DomainException('You cannot review yourself.');
        }

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
