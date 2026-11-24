<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingSource: string
{
    case Website = 'website';
    case Whatsapp = 'whatsapp';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'الموقع',
            self::Whatsapp => 'واتساب',
            self::Manual => 'يدوي',
        };
    }
}
