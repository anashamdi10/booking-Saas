<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled maintenance
|--------------------------------------------------------------------------
|
| Run the scheduler every minute on production:
|   * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
|
*/

Schedule::call(function (): void {
    // Subscriptions that reached their end date stop granting storage quota.
    Subscription::query()
        ->where('status', SubscriptionStatus::Active->value)
        ->whereNotNull('ends_at')
        ->where('ends_at', '<', now())
        ->update(['status' => SubscriptionStatus::Expired->value]);
})->dailyAt('01:15')->name('expire-subscriptions');

Schedule::command('queue:prune-batches --hours=48')->daily()->name('prune-queue-batches');
Schedule::command('auth:clear-resets')->daily()->name('clear-expired-password-resets');
