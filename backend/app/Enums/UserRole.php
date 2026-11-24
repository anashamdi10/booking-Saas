<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Photographer = 'photographer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'مدير النظام',
            self::Photographer => 'مصور',
        };
    }
}
