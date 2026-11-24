<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار التأكيد',
            self::Confirmed => 'مؤكد',
            self::Completed => 'مكتمل',
            self::Cancelled => 'ملغي',
            self::Rejected => 'مرفوض',
        };
    }

    /**
     * States that still hold the time slot on the photographer's calendar.
     */
    public function occupiesSlot(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Completed], true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }
}
