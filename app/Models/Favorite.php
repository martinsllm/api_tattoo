<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    protected $fillable = [
        'user_id',
        'artist_profile_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function artistProfile()
    {
        return $this->belongsTo(ArtistProfile::class);
    }
}
