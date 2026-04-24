<?php

namespace App\Services;

use App\Models\ArtistProfile;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;

class ReviewService
{

    public function create(array $data)
    {
        $user = Auth::user();

        // impedir autoavaliação
        $artist = ArtistProfile::findOrFail($data['artist_profile_id']);

        if ($artist->user_id === $user->id) {
            throw new \DomainException('You cannot review yourself.');
        }

        //impedir avaliações duplicadas
        $existingReview = $artist->reviews()->where('user_id', $user->id)->first();

        if ($existingReview) {
            throw new \DomainException('You have already reviewed this artist.');
        }

        return Review::create([
            'user_id' => $user->id,
            'artist_profile_id' => $data['artist_profile_id'],
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);

    }
    
}