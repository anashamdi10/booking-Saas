<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the photographer when a visitor requests a booking: stored in the
 * dashboard (bell) and mailed to the address on the account.
 */
class NewBookingForPhotographer extends Notification
{
    use BuildsArabicMail;

    public function __construct(private readonly Booking $booking) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $booking = $this->booking;

        return [
            'title' => 'طلب حجز جديد',
            'reference' => $booking->reference,
            'booking_id' => $booking->id,
            'customer_name' => $booking->customer_name,
            'start_at' => $booking->start_at?->toIso8601String(),
            'package_name' => $booking->package?->name,
            'url' => '/photographer/bookings',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;

        $lines = [
            'وصل طلب حجز جديد برقم '.$booking->reference.' بانتظار ردك.',
            'العميل: '.$booking->customer_name,
            'الموعد: '.$this->formatSlot(),
        ];

        if ($booking->package !== null) {
            $lines[] = 'الباقة: '.$booking->package->name;
        }

        if ($booking->total_price !== null) {
            $lines[] = 'القيمة: '.number_format((float) $booking->total_price, 2).' '.$booking->currency;
        }

        if (filled($booking->customer_phone)) {
            $lines[] = 'جوال العميل: '.$booking->customer_phone;
        }

        $lines[] = 'يرجى تأكيد الموعد أو رفض الطلب في أقرب وقت.';

        return $this->arabicMail(
            subject: 'طلب حجز جديد — '.$booking->reference,
            heading: 'أهلاً '.$notifiable->name.'،',
            lines: $lines,
            action: [
                'text' => 'مراجعة الحجز',
                'url' => config('app.frontend_url').'/photographer/bookings',
            ],
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
