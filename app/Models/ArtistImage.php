<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtistImage extends Model
{
    protected $fillable = [
        'artist_profile_id',
        'image_url',
        'thumbnail_url',
        'description',
        'is_main',
    ];

    public function artist()
    {
        return $this->belongsTo(ArtistProfile::class, 'artist_profile_id');
    }
}
