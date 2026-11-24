<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Booking;
use App\Models\Package;
use App\Models\PhotographerProfile;
use App\Models\WorkingHour;
use App\Policies\BookingPolicy;
use App\Policies\PackagePolicy;
use App\Policies\PhotographerProfilePolicy;
use App\Policies\WorkingHourPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private const POLICIES = [
        Booking::class => BookingPolicy::class,
        Package::class => PackagePolicy::class,
        PhotographerProfile::class => PhotographerProfilePolicy::class,
        WorkingHour::class => WorkingHourPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        $this->configureRateLimiting();
    }

    /**
     * Rate limits keep the API resilient against abuse and credential stuffing.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)
            ->by($request->ip().'|'.strtolower((string) $request->input('email'))));

        RateLimiter::for('booking', fn (Request $request) => Limit::perHour(60)
            ->by($request->ip()));

        // A single customer cannot flood a photographer with requests.
        RateLimiter::for('booking-per-email', fn (Request $request) => Limit::perHour(5)
            ->by(strtolower((string) $request->input('customer_email')).'|'.$request->ip()));

        RateLimiter::for('public-read', fn (Request $request) => Limit::perMinute(60)
            ->by($request->ip()));

        /*
         * Rotating a password or the sign-in address is limited per ACCOUNT,
         * not per IP: colleagues behind one NAT must not lock each other out,
         * and a throwaway account never eats a demo account's budget — which
         * is what happens when the smoke test and the browser test run within
         * the same minute behind 127.0.0.1.
         */
        RateLimiter::for('credentials', fn (Request $request) => Limit::perMinute(5)
            ->by($request->path().'|'.($request->user()?->id ?: $request->ip())));
    }
}
