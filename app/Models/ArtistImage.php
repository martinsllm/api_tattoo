<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read ArtistProfile|null $artist
 */
class ArtistImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'artist_profile_id',
        'image_url',
        'thumbnail_url',
        'description',
        'is_main',
        'position',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(ArtistProfile::class, 'artist_profile_id');
    }
}
