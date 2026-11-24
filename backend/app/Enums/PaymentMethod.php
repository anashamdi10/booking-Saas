<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقداً',
            self::BankTransfer => 'تحويل بنكي',
            self::Card => 'بطاقة',
            self::Other => 'أخرى',
        };
    }
}
