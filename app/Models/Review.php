<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'artist_profile_id',
        'user_id',
        'rating',
        'comment',
    ];

    public function artist()
    {
        return $this->belongsTo(ArtistProfile::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
