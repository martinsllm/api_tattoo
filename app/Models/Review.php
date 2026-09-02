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
        'reply',
        'replied_at',
    ];

    protected $casts = [
        'replied_at' => 'datetime',
    ];

    public function artist()
    {
        return $this->belongsTo(ArtistProfile::class, 'artist_profile_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
