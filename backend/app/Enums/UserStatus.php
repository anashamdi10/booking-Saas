<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد المراجعة',
            self::Active => 'نشط',
            self::Suspended => 'موقوف',
        };
    }
}
