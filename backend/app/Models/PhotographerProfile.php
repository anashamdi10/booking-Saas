<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LandingTemplate;
use Database\Factories\PhotographerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhotographerProfile extends Model
{
    /** @use HasFactory<PhotographerProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'slug',
        'headline',
        'bio',
        'city',
        'country',
        'phone',
        'whatsapp',
        'website',
        'logo_path',
        'cover_path',
        'theme',
        'landing_template',
        'years_experience',
        'rating_avg',
        'rating_count',
        'is_public',
        'is_featured',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'theme' => 'array',
            'landing_template' => LandingTemplate::class,
            'rating_avg' => 'decimal:2',
            'years_experience' => 'integer',
            'rating_count' => 'integer',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function portfolioImages(): HasMany
    {
        return $this->user->portfolioImages()->orderBy('sort_order');
    }

    /**
     * Default theme colours applied when the photographer has not customised them.
     */
    public function themeColors(): array
    {
        return array_merge([
            'primary' => '#111827',
            'accent' => '#f59e0b',
            'background' => '#ffffff',
            'surface' => '#f8fafc',
            'text' => '#0f172a',
        ], $this->theme ?? []);
    }
}
