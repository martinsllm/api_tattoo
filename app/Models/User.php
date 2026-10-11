<?php

namespace App\Models;

use App\Notifications\EmailVerificationNotification;
use App\Notifications\PasswordResetNotification;
use App\Notifications\PendingEmailChangeNotification;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property-read ArtistProfile|null $artistProfile
 */
#[Fillable(['name', 'email', 'password', 'accepted_terms_at'])]
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
            'accepted_terms_at' => 'datetime',
            'artist_catalog_suppressed_for_pending_email' => 'boolean',
        ];
    }

    /**
     * @return HasOne<ArtistProfile, $this>
     */
    public function artistProfile(): HasOne
    {
        return $this->hasOne(ArtistProfile::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(ArtistProfile::class, 'favorites')
            ->withTimestamps();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->rotateEmailVerificationToken();
        $this->notify(new EmailVerificationNotification);
    }

    public function sendPendingEmailChangeNotification(): void
    {
        $this->notify(new PendingEmailChangeNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PasswordResetNotification($token));
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

    public function rotateEmailVerificationToken(): string
    {
        $token = Str::random(64);
        $this->forceFill([
            'email_verification_token' => $token,
        ])->save();

        return $token;
    }

    public function rotatePendingEmailToken(): string
    {
        $token = Str::random(64);
        $this->forceFill([
            'pending_email_token' => $token,
        ])->save();

        return $token;
    }
}
