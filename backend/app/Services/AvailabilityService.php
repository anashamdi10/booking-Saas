<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Package;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Computes the bookable slots of a photographer.
 *
 * A slot is free when it:
 *  1. falls inside one of the photographer's working hours,
 *  2. starts in the future,
 *  3. does not overlap a manual availability block,
 *  4. does not overlap an open/confirmed booking.
 */
class AvailabilityService
{
    private const MAX_RANGE_DAYS = 60;

    /**
     * @return Collection<int, array{date: string, slots: Collection<int, array{start: string, end: string, label: string}>}>
     */
    public function slotsFor(
        int $photographerId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?Package $package = null,
    ): Collection {
        $from = CarbonImmutable::instance($from)->startOfDay();
        $to = CarbonImmutable::instance($to)->startOfDay();

        if ($to->lessThan($from)) {
            return collect();
        }

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            $to = $from->addDays(self::MAX_RANGE_DAYS);
        }

        $workingHours = WorkingHour::query()
            ->where('photographer_id', $photographerId)
            ->where('is_active', true)
            ->get()
            ->groupBy('weekday');

        if ($workingHours->isEmpty()) {
            return collect();
        }

        $duration = $package?->duration_minutes ?? 60;
        $duration = max(15, (int) $duration);

        $now = CarbonImmutable::now();
        $busy = $this->busyIntervals($photographerId, $from, $to);
        $blocks = $this->blockedIntervals($photographerId, $from, $to);

        $days = collect();

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $weekday = $date->dayOfWeek;
            $hours = $workingHours->get($weekday);

            if ($hours === null || $hours->isEmpty()) {
                continue;
            }

            $slots = collect();

            foreach ($hours as $hour) {
                $interval = max(15, (int) $hour->slot_interval_minutes);
                $cursor = $date->setTimeFromTimeString($hour->start_time);
                $limit = $date->setTimeFromTimeString($hour->end_time);

                while ($cursor->lessThan($limit)) {
                    $slotEnd = $cursor->addMinutes($duration);

                    if ($slotEnd->greaterThan($limit)) {
                        break;
                    }

                    if ($cursor->greaterThan($now)
                        && ! $this->overlapsAny($cursor, $slotEnd, $busy)
                        && ! $this->overlapsAny($cursor, $slotEnd, $blocks)
                    ) {
                        $slots->push([
                            'start' => $cursor->toIso8601String(),
                            'end' => $slotEnd->toIso8601String(),
                            'label' => $cursor->format('H:i'),
                        ]);
                    }

                    $cursor = $cursor->addMinutes($interval);
                }
            }

            if ($slots->isNotEmpty()) {
                $days->push([
                    'date' => $date->toDateString(),
                    'slots' => $slots->values(),
                ]);
            }
        }

        return $days->values();
    }

    /**
     * True when the given range is inside the photographer's working hours and not blocked.
     */
    public function isWithinWorkingHours(int $photographerId, CarbonInterface $start, CarbonInterface $end): bool
    {
        $start = CarbonImmutable::instance($start);
        $end = CarbonImmutable::instance($end);

        if ($end->lessThanOrEqualTo($start)) {
            return false;
        }

        $hours = WorkingHour::query()
            ->where('photographer_id', $photographerId)
            ->where('weekday', $start->dayOfWeek)
            ->where('is_active', true)
            ->get();

        foreach ($hours as $hour) {
            $windowStart = $start->setTimeFromTimeString($hour->start_time);
            $windowEnd = $start->setTimeFromTimeString($hour->end_time);

            if ($start->greaterThanOrEqualTo($windowStart) && $end->lessThanOrEqualTo($windowEnd)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, array{start: CarbonInterface, end: CarbonInterface}>
     */
    private function busyIntervals(int $photographerId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Booking::query()
            ->where('photographer_id', $photographerId)
            ->whereIn('status', [
                BookingStatus::Pending->value,
                BookingStatus::Confirmed->value,
                BookingStatus::Completed->value,
            ])
            ->where('start_at', '<', $to->endOfDay())
            ->where('end_at', '>', $from->startOfDay())
            ->get(['start_at', 'end_at'])
            ->map(fn (Booking $booking) => [
                'start' => CarbonImmutable::instance($booking->start_at),
                'end' => CarbonImmutable::instance($booking->end_at),
            ]);
    }

    /**
     * @return Collection<int, array{start: CarbonInterface, end: CarbonInterface}>
     */
    private function blockedIntervals(int $photographerId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return AvailabilityBlock::query()
            ->where('photographer_id', $photographerId)
            ->where('starts_at', '<', $to->endOfDay())
            ->where('ends_at', '>', $from->startOfDay())
            ->get(['starts_at', 'ends_at'])
            ->map(fn (AvailabilityBlock $block) => [
                'start' => CarbonImmutable::instance($block->starts_at),
                'end' => CarbonImmutable::instance($block->ends_at),
            ]);
    }

    /**
     * @param  Collection<int, array{start: CarbonInterface, end: CarbonInterface}>  $intervals
     */
    private function overlapsAny(CarbonInterface $start, CarbonInterface $end, Collection $intervals): bool
    {
        foreach ($intervals as $interval) {
            if ($start->lessThan($interval['end']) && $end->greaterThan($interval['start'])) {
                return true;
            }
        }

        return false;
    }
}
