<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Notifications\BookingConfirmationForCustomer;
use App\Notifications\NewBookingForPhotographer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Tells both sides about a newly requested booking: the photographer gets an
 * in-dashboard notification plus an email, the visitor gets a confirmation
 * email at the address they left on the form.
 *
 * Delivering a notification must never undo a booking that is already
 * committed, so every send is best-effort and failures are only logged.
 */
class BookingNotifier
{
    public function bookingCreated(Booking $booking): void
    {
        $this->notifyPhotographer($booking);
        $this->notifyCustomer($booking);
    }

    private function notifyPhotographer(Booking $booking): void
    {
        $photographer = $booking->photographer;

        if ($photographer === null) {
            return;
        }

        try {
            $photographer->notify(new NewBookingForPhotographer($booking));
        } catch (Throwable $exception) {
            Log::warning('Photographer booking notification failed.', [
                'booking' => $booking->reference,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function notifyCustomer(Booking $booking): void
    {
        if (! filled($booking->customer_email)) {
            return;
        }

        try {
            Notification::route('mail', $booking->customer_email)
                ->notify(new BookingConfirmationForCustomer($booking));
        } catch (Throwable $exception) {
            Log::warning('Customer booking confirmation failed.', [
                'booking' => $booking->reference,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
