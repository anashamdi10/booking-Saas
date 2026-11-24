<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'معلق',
            self::Active => 'نشط',
            self::Expired => 'منتهي',
            self::Cancelled => 'ملغي',
        };
    }

    public function grantsStorage(): bool
    {
        return $this === self::Active;
    }
}
