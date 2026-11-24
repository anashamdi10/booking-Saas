<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Builds public URLs for media assets.
 *
 * Assets may either live on the configured disk (normal case) or be served
 * from an external CDN URL that was stored directly in the database.
 */
final class MediaUrl
{
    public static function make(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk(config('media.public_disk'))->url($path);
    }
}
