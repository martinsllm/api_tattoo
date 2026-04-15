<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtistProfile extends Model
{
    protected $fillable = [
        'user_id',
        'studio_name',
        'bio',
        'phone',
        'instagram',
        'address',
        'city',
        'state',
        'latitude',
        'longitude',
        'is_active',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->hasMany(ArtistImage::class);
    }

    public function styles()
    {
        return $this->belongsToMany(Style::class, 'artist_style');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function favoritedBy()
    {
        return $this->hasMany(Favorite::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'artist_tag');
    }
}
