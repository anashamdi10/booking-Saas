<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Package;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates bookings while guaranteeing that no two open bookings overlap.
 */
class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingNotifier $notifier,
    ) {}

    /**
     * @param  array{start_at: CarbonImmutable, package_id?: int|null, customer_name: string, customer_email: string, customer_phone?: string|null, notes?: string|null, source?: BookingSource}  $data
     */
    public function createForPhotographer(User $photographer, array $data): Booking
    {
        $package = null;

        if (! empty($data['package_id'])) {
            // A deactivated package is hidden from the public page; the API must
            // not accept it either, otherwise the switch would be trivial to bypass.
            $package = Package::query()
                ->where('photographer_id', $photographer->id)
                ->where('is_active', true)
                ->findOrFail($data['package_id']);
        }

        $start = CarbonImmutable::instance($data['start_at']);
        $duration = $package?->duration_minutes ?? 60;
        $end = $start->addMinutes(max(15, (int) $duration));

        if ($start->isPast()) {
            throw new RuntimeException('لا يمكن الحجز في وقت سابق.');
        }

        if (! $this->availability->isWithinWorkingHours($photographer->id, $start, $end)) {
            throw new RuntimeException('الموعد المطلوب خارج ساعات العمل المحددة.');
        }

        $booking = DB::transaction(function () use ($photographer, $package, $start, $end, $data): Booking {
            $this->assertNoOverlap($photographer->id, $start, $end);

            return Booking::query()->create([
                'reference' => $this->uniqueReference(),
                'photographer_id' => $photographer->id,
                'package_id' => $package?->id,
                'client_id' => null,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'start_at' => $start,
                'end_at' => $end,
                'status' => BookingStatus::Pending,
                'source' => $data['source'] ?? BookingSource::Website,
                'notes' => $data['notes'] ?? null,
                'total_price' => $package?->price,
                'currency' => $package?->currency ?? 'SAR',
            ]);
        });

        // Only once the row is committed do both parties get told about it, so a
        // rolled back transaction never produces a notification.
        $this->notifier->bookingCreated($booking);

        return $booking;
    }

    /**
     * @throws RuntimeException when the slot was taken in the meantime.
     */
    private function assertNoOverlap(int $photographerId, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $conflict = Booking::query()
            ->where('photographer_id', $photographerId)
            ->whereIn('status', [
                BookingStatus::Pending->value,
                BookingStatus::Confirmed->value,
                BookingStatus::Completed->value,
            ])
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->lockForUpdate()
            ->first();

        if ($conflict !== null) {
            throw new RuntimeException('هذا الموعد محجوز بالفعل، يرجى اختيار موعد آخر.');
        }
    }

    private function uniqueReference(): string
    {
        do {
            $reference = Booking::generateReference();
        } while (Booking::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
