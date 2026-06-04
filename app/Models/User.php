<?php

namespace App\Models;

use App\Notifications\EmailVerificationNotification;
use App\Notifications\PendingEmailChangeNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, MustVerifyEmailTrait, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function artistProfile()
    {
        return $this->hasOne(ArtistProfile::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(ArtistProfile::class, 'favorites')
            ->withTimestamps();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new EmailVerificationNotification);
    }

    public function sendPendingEmailChangeNotification(): void
    {
        $this->notify(new PendingEmailChangeNotification);
    }

    /**
     * @return array<int, string>|string
     */
    public function routeNotificationForMail(Notification $notification): array|string
    {
        if ($notification instanceof PendingEmailChangeNotification) {
            return $this->pending_email;
        }

        return $this->email;
    }
}
