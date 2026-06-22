<?php

namespace App\Models;

use Database\Factories\ArtistProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArtistProfile extends Model
{
    /** @use HasFactory<ArtistProfileFactory> */
    use HasFactory;

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

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
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
        return $this->belongsToMany(User::class, 'favorites')
            ->withTimestamps();
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
        return $query->select('artist_profiles.*')
            ->selectRaw(self::haversineSql().' AS distance', [$lat, $lng, $lat]);
    }

    public function scopeWithinRadius($query, $lat, $lng, $radius)
    {
        return $query->whereRaw(self::haversineSql().' <= ?', [$lat, $lng, $lat, $radius]);
    }

    private static function haversineSql(): string
    {
        return '(6371 * acos(
                cos(radians(?))
                * cos(radians(latitude))
                * cos(radians(longitude) - radians(?))
                + sin(radians(?))
                * sin(radians(latitude))
            ))';
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

    public function scopeFilterCity($query, string $city)
    {
        return $query->where('city', 'like', '%'.$city.'%');
    }

    public function scopeFilterState($query, string $state)
    {
        return $query->where('state', $state);
    }

    public function scopeFilterStudioName($query, string $studioName)
    {
        return $query->where('studio_name', 'like', '%'.$studioName.'%');
    }
}
