<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GalleryAccessType;
use App\Enums\GalleryStatus;
use Database\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Gallery extends Model
{
    /** @use HasFactory<GalleryFactory> */
    use HasFactory;

    protected $fillable = [
        'photographer_id',
        'client_id',
        'title',
        'slug',
        'description',
        'access_type',
        'access_email',
        'access_password',
        'share_token',
        'expires_at',
        'status',
        'views_count',
        'downloads_enabled',
        'is_published',
        'published_at',
    ];

    protected $hidden = [
        'access_password',
        'share_token',
    ];

    protected function casts(): array
    {
        return [
            'access_type' => GalleryAccessType::class,
            'status' => GalleryStatus::class,
            'expires_at' => 'datetime',
            'published_at' => 'datetime',
            'views_count' => 'integer',
            'downloads_enabled' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(GalleryFile::class)->orderBy('sort_order');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('status', GalleryStatus::Published->value);
    }

    public static function generateShareToken(): string
    {
        return (string) Str::uuid();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
