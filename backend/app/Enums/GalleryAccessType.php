<?php

declare(strict_types=1);

namespace App\Enums;

enum GalleryAccessType: string
{
    case PublicLink = 'public_link';
    case EmailPassword = 'email_password';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::PublicLink => 'رابط عام',
            self::EmailPassword => 'بريد وكلمة مرور',
            self::Private => 'خاص',
        };
    }
}
