<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Media disks
    |--------------------------------------------------------------------------
    |
    | "public_disk" holds assets that are safe to serve publicly (profile
    | logos, covers, portfolio samples). "private_disk" holds client photos
    | and gallery files which are only reachable through short-lived signed
    | URLs.
    |
    | In production both point to an S3 compatible bucket (AWS S3 or
    | Cloudflare R2).
    |
    */

    'public_disk' => env('MEDIA_PUBLIC_DISK', 'r2'),

    'private_disk' => env('MEDIA_PRIVATE_DISK', 'media'),

    /*
    |--------------------------------------------------------------------------
    | Upload limits
    |--------------------------------------------------------------------------
    */

    'max_upload_kb' => (int) env('MEDIA_MAX_UPLOAD_KB', 20480),

    'allowed_mimes' => [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif',
    ],

];
