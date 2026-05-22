<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasFactory, SoftDeletes;

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
