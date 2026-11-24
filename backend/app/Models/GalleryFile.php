<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GalleryFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryFile extends Model
{
    /** @use HasFactory<GalleryFileFactory> */
    use HasFactory;

    protected $fillable = [
        'gallery_id',
        'disk',
        'path',
        'thumbnail_path',
        'original_name',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'checksum',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}
