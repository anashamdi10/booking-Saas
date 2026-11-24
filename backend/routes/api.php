<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Api\V1\Admin\PhotographerController as AdminPhotographerController;
use App\Http\Controllers\Api\V1\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\Photographer\DashboardController;
use App\Http\Controllers\Api\V1\Photographer\NotificationController;
use App\Http\Controllers\Api\V1\Photographer\PackageController;
use App\Http\Controllers\Api\V1\Photographer\ProfileController;
use App\Http\Controllers\Api\V1\PhotographerController;
use App\Http\Controllers\Api\V1\SiteController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    /*
     |----------------------------------------------------------------------
     | Public endpoints
     |----------------------------------------------------------------------
     |
     | These responses never depend on the authenticated user, so they may be
     | cached by the browser and by a CDN in front of the API.
     |
     */
    Route::middleware(['throttle:public-read', 'cache.public', 'track.visit'])->group(function (): void {
        Route::get('site/stats', [SiteController::class, 'stats']);
        Route::get('photographers', [PhotographerController::class, 'index']);
        Route::get('photographers/{slug}', [PhotographerController::class, 'show']);
    });

    Route::get('photographers/{slug}/availability', [AvailabilityController::class, 'index'])
        ->middleware('throttle:public-read');

    /*
     |----------------------------------------------------------------------
     | Public booking requests
     |----------------------------------------------------------------------
     */
    Route::post('bookings', [BookingController::class, 'store'])
        ->middleware(['throttle:booking', 'throttle:booking-per-email']);

    /*
     |----------------------------------------------------------------------
     | Authentication
     |----------------------------------------------------------------------
     */
    Route::prefix('auth')->middleware('throttle:auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    /*
     |----------------------------------------------------------------------
     | Authenticated endpoints
     |----------------------------------------------------------------------
     */
    Route::middleware(['auth:sanctum', 'user.active'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // Both rotate credentials: five attempts per minute for each account
        // (see the `credentials` limiter), and a success is never throttled
        // away from the user who just proved they own the password.
        Route::put('auth/email', [AuthController::class, 'changeEmail'])->middleware('throttle:credentials');
        Route::put('auth/password', [AuthController::class, 'changePassword'])->middleware('throttle:credentials');

        Route::prefix('photographer')->middleware('photographer')->group(function (): void {
            Route::get('dashboard', [DashboardController::class, 'index']);
            Route::get('profile', [ProfileController::class, 'show']);
            Route::put('profile', [ProfileController::class, 'update']);
            Route::put('profile/working-hours', [ProfileController::class, 'updateWorkingHours']);

            Route::apiResource('packages', PackageController::class);

            Route::get('bookings', [BookingController::class, 'index']);
            Route::patch('bookings/{booking}', [BookingController::class, 'update']);

            Route::get('notifications', [NotificationController::class, 'index']);
            Route::patch('notifications/read-all', [NotificationController::class, 'readAll']);
            Route::patch('notifications/{notification}/read', [NotificationController::class, 'read']);
        });

        Route::prefix('admin')->middleware('admin')->group(function (): void {
            Route::get('photographers', [AdminPhotographerController::class, 'index']);
            Route::patch('photographers/{photographer}', [AdminPhotographerController::class, 'update']);

            /*
             * Subscription plans (the ones the admin assigns to a
             * photographer). They are managed as a plain resource: the same
             * screens that pick a plan here can also create, edit or retire
             * one without touching a developer.
             */
            Route::get('plans', [AdminPlanController::class, 'index']);
            Route::post('plans', [AdminPlanController::class, 'store']);
            Route::put('plans/{plan}', [AdminPlanController::class, 'update']);
            Route::delete('plans/{plan}', [AdminPlanController::class, 'destroy']);

            /*
             * The admin acts on a NAMED photographer's packages. Keeping the
             * photographer segment in every path (including edits and
             * deletes) makes each URL say what it actually touches.
             */
            Route::get('photographers/{photographer}/packages', [AdminPackageController::class, 'index']);
            Route::post('photographers/{photographer}/packages', [AdminPackageController::class, 'store']);
            Route::put('photographers/{photographer}/packages/{package}', [AdminPackageController::class, 'update']);
            Route::delete('photographers/{photographer}/packages/{package}', [AdminPackageController::class, 'destroy']);
        });
    });
});
