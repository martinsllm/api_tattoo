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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithDistance($query, $lat, $lng)
    {
        return $query->select('artist_profiles.*')->selectRaw("
            (6371 * acos(
                cos(radians(?)) 
                * cos(radians(latitude)) 
                * cos(radians(longitude) - radians(?)) 
                + sin(radians(?)) 
                * sin(radians(latitude))
            )) AS distance
        ", [$lat, $lng, $lat]);
    }

    public function scopeWithinRadius($query, $radius)
    {
        return $query->having('distance', '<=', $radius)
                    ->orderBy('distance');
    }

    public function scopeFilterStyles($query, $styles)
    {
        return $query->whereHas('styles', function ($q) use ($styles) {
            $q->whereIn('styles.id', $styles);
        });
    }

    public function scopeFilterTags($query, $tags)
    {
        return $query->whereHas('tags', function ($q) use ($tags) {
            $q->whereIn('tags.id', $tags);
        });
    }
}
