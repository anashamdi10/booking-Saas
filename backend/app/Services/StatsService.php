<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\DailyStat;
use App\Models\Invoice;
use App\Models\SiteVisit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates visitor / booking / revenue metrics for the public site,
 * the admin dashboard and each photographer.
 */
class StatsService
{
    public function siteStats(): array
    {
        $now = CarbonImmutable::now();

        return [
            'photographers_count' => User::query()
                ->publiclyListed()
                ->where('role', UserRole::Photographer->value)
                ->count(),
            'cities_count' => DB::table('photographer_profiles')
                ->join('users', 'users.id', '=', 'photographer_profiles.user_id')
                ->where('users.status', UserStatus::Active->value)
                ->whereNotNull('photographer_profiles.city')
                ->distinct()
                ->count('photographer_profiles.city'),
            'bookings_count' => Booking::query()->count(),
            'completed_bookings_count' => Booking::query()
                ->where('status', BookingStatus::Completed->value)
                ->count(),
            'visits_total' => (int) SiteVisit::query()->count(),
            'visits_today' => SiteVisit::query()
                ->whereDate('visited_at', $now->toDateString())
                ->count(),
            'invoices_paid_total' => (float) Invoice::query()->paid()->sum('total'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function photographerStats(User $photographer): array
    {
        $bookings = Booking::query()->where('photographer_id', $photographer->id);

        return [
            'bookings_total' => (clone $bookings)->count(),
            'bookings_pending' => (clone $bookings)->where('status', BookingStatus::Pending->value)->count(),
            'bookings_confirmed' => (clone $bookings)->where('status', BookingStatus::Confirmed->value)->count(),
            'bookings_completed' => (clone $bookings)->where('status', BookingStatus::Completed->value)->count(),
            'visits_total' => SiteVisit::query()->where('photographer_id', $photographer->id)->count(),
            'visits_today' => SiteVisit::query()
                ->where('photographer_id', $photographer->id)
                ->whereDate('visited_at', CarbonImmutable::now()->toDateString())
                ->count(),
            'revenue_paid' => (float) Invoice::query()
                ->where('photographer_id', $photographer->id)
                ->paid()
                ->sum('total'),
            'revenue_outstanding' => (float) Invoice::query()
                ->where('photographer_id', $photographer->id)
                ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Overdue->value])
                ->sum('total'),
        ];
    }

    /**
     * Daily series used by the dashboards' charts.
     *
     * @return array<int, array{date: string, visits: int, bookings: int, revenue: float}>
     */
    public function dailySeries(?User $photographer, int $days = 30): array
    {
        $start = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        $visits = SiteVisit::query()
            ->when($photographer !== null, fn ($query) => $query->where('photographer_id', $photographer->id))
            ->where('visited_at', '>=', $start)
            ->get(['visited_at'])
            ->groupBy(fn ($visit) => CarbonImmutable::instance($visit->visited_at)->toDateString())
            ->map(fn ($group) => $group->count());

        $bookings = Booking::query()
            ->when($photographer !== null, fn ($query) => $query->where('photographer_id', $photographer->id))
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn ($booking) => CarbonImmutable::instance($booking->created_at)->toDateString())
            ->map(fn ($group) => $group->count());

        $revenue = DailyStat::query()
            ->when($photographer !== null, fn ($query) => $query->where('photographer_id', $photographer->id))
            ->where('date', '>=', $start->toDateString())
            ->get(['date', 'revenue'])
            ->groupBy(fn ($stat) => CarbonImmutable::instance($stat->date)->toDateString())
            ->map(fn ($group) => (float) $group->sum('revenue'));

        $series = [];

        for ($day = $start; $day->lessThanOrEqualTo(CarbonImmutable::now()); $day = $day->addDay()) {
            $key = $day->toDateString();

            $series[] = [
                'date' => $key,
                'visits' => (int) ($visits[$key] ?? 0),
                'bookings' => (int) ($bookings[$key] ?? 0),
                'revenue' => (float) ($revenue[$key] ?? 0.0),
            ];
        }

        return $series;
    }
}
