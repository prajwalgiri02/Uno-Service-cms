<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name', 'email', 'phone', 'password', 'area', 'language', 'nudges_enabled', 'paused_until', 'pings_per_day',
    'points_balance', 'email_verified_at', 'phone_verified_at', 'blocked_at', 'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'paused_until' => 'datetime',
            'blocked_at' => 'datetime',
            'last_login_at' => 'datetime',
            'nudges_enabled' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * All roles live on the web guard. Without this, Spatie follows the request's
     * guard, and role lookups inside API requests would search the api guard.
     */
    public function getDefaultGuardName(): string
    {
        return 'web';
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(UserRole::ADMIN->value);
    }

    public function isVendor(): bool
    {
        return $this->hasRole(UserRole::VENDOR->value);
    }

    public function isPaused(): bool
    {
        return $this->paused_until?->isFuture() ?? false;
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->phone ? 'User '.substr($this->phone, -4) : 'User #'.$this->id);
    }

    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class);
    }

    public function errands(): HasMany
    {
        return $this->hasMany(Errand::class);
    }

    public function freeWindows(): HasMany
    {
        return $this->hasMany(FreeWindow::class)->orderBy('from_time');
    }

    public function blockedPlaces(): HasMany
    {
        return $this->hasMany(BlockedPlace::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function nudges(): HasMany
    {
        return $this->hasMany(Nudge::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function favoriteShops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'favorites')->withTimestamps();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }
}
