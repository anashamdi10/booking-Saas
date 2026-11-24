<?php

declare(strict_types=1);

namespace App\Enums;

enum LandingTemplate: string
{
    case Classic = 'classic';
    case Minimal = 'minimal';
    case Showcase = 'showcase';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'كلاسيكي',
            self::Minimal => 'بسيط',
            self::Showcase => 'معرض أعمال',
        };
    }
}
