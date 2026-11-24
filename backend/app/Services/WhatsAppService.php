<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PhotographerProfile;

/**
 * Builds external WhatsApp deep links (wa.me) so customers can talk to a photographer
 * without the platform handling the conversation.
 */
class WhatsAppService
{
    private const DEFAULT_COUNTRY_CODE = '966';

    public function linkForProfile(?PhotographerProfile $profile, ?string $message = null): ?string
    {
        if ($profile === null || blank($profile->whatsapp)) {
            return null;
        }

        $number = $this->normalizeNumber($profile->whatsapp, $profile->country);

        if ($number === null) {
            return null;
        }

        return $this->build($number, $message ?? $this->defaultMessage($profile));
    }

    public function build(string $number, string $message): string
    {
        return sprintf(
            'https://wa.me/%s?text=%s',
            $number,
            rawurlencode($message),
        );
    }

    public function normalizeNumber(string $raw, ?string $countryCode = null): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        // Local number: 05xxxxxxxx -> country code + national significant number.
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) <= 9) {
            $code = ltrim($countryCode ?? self::DEFAULT_COUNTRY_CODE, '+');
            $digits = $code.ltrim($digits, '0');
        }

        return $digits;
    }

    private function defaultMessage(PhotographerProfile $profile): string
    {
        return sprintf(
            'مرحباً، أنا شاهدت صفحتك على منصة حجوزات المصورين (%s) وأرغب في حجز موعد تصوير.',
            $profile->headline ?? $profile->user->name,
        );
    }
}
