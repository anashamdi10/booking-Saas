<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PortfolioImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioImage extends Model
{
    /** @use HasFactory<PortfolioImageFactory> */
    use HasFactory;

    protected $fillable = [
        'photographer_id',
        'disk',
        'path',
        'thumbnail_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'sort_order',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }
}
