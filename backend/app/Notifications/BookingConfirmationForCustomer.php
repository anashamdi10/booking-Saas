<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirmation mailed to the visitor who requested the booking. They have no
 * account, so this notification only ever travels through the mail channel.
 */
class BookingConfirmationForCustomer extends Notification
{
    use BuildsArabicMail;

    public function __construct(private readonly Booking $booking) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $photographer = $booking->photographer;

        $lines = [
            'تم استلام طلب الحجز رقم '.$booking->reference.' بنجاح.',
            'المصور: '.($photographer?->name ?? '—'),
            'الموعد: '.$this->formatSlot(),
        ];

        if ($booking->package !== null) {
            $lines[] = 'الباقة: '.$booking->package->name;
        }

        if ($booking->total_price !== null) {
            $lines[] = 'القيمة: '.number_format((float) $booking->total_price, 2).' '.$booking->currency;
        }

        $lines[] = 'الحالة الحالية: بانتظار تأكيد المصور.';
        $lines[] = 'سيتواصل معك المصور عبر الجوال أو واتساب لتأكيد الموعد.';

        $slug = $photographer?->profile?->slug;

        return $this->arabicMail(
            subject: 'تم استلام طلب الحجز '.$booking->reference,
            heading: 'أهلاً '.$booking->customer_name.'،',
            lines: $lines,
            action: $slug !== null
                ? [
                    'text' => 'زيارة صفحة المصور',
                    'url' => config('app.frontend_url').'/photographers/'.$slug,
                ]
                : null,
        );
    }

    private function formatSlot(): string
    {
        $start = $this->booking->start_at;

        if ($start === null) {
            return '—';
        }

        return $start->copy()->locale('ar')->isoFormat('dddd، D MMMM YYYY — h:mm A');
    }
}
