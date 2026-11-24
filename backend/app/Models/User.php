<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;

    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'status',
        'avatar_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(PhotographerProfile::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class, 'photographer_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'photographer_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'photographer_id');
    }

    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class, 'photographer_id');
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class, 'photographer_id');
    }

    public function availabilityBlocks(): HasMany
    {
        return $this->hasMany(AvailabilityBlock::class, 'photographer_id');
    }

    public function portfolioImages(): HasMany
    {
        return $this->hasMany(PortfolioImage::class, 'photographer_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'photographer_id');
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'photographer_id')
            ->where('status', SubscriptionStatus::Active->value)
            ->latestOfMany();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'photographer_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'photographer_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isPhotographer(): bool
    {
        return $this->role === UserRole::Photographer;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Photographers that are visible to the public: active account + published profile.
     */
    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query
            ->where('status', UserStatus::Active->value)
            ->whereHas('profile', fn (Builder $profile) => $profile->where('is_public', true));
    }
}
