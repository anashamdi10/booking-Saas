<?php

declare(strict_types=1);

use App\Http\Middleware\CachePublicResponse;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsurePhotographer;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResetAuthenticationState;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackSiteVisit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            ResetAuthenticationState::class,
        ]);

        $middleware->api(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'photographer' => EnsurePhotographer::class,
            'user.active' => EnsureUserIsActive::class,
            'track.visit' => TrackSiteVisit::class,
            'cache.public' => CachePublicResponse::class,
        ]);

        // The app always runs behind a reverse proxy (Nginx / Cloudflare) in production.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // Validation failures keep their per-field messages so the SPA can
            // highlight the offending inputs.
            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => $e->validator->errors()->first() ?: 'البيانات المدخلة غير صحيحة.',
                    'errors' => $e->errors(),
                ], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'يجب تسجيل الدخول أولاً.', 'errors' => []], 401);
            }

            if ($e instanceof AuthorizationException) {
                return response()->json(['message' => 'لا تملك صلاحية الوصول لهذه العملية.', 'errors' => []], 403);
            }

            if ($e instanceof ModelNotFoundException) {
                return response()->json(['message' => 'العنصر المطلوب غير موجود.', 'errors' => []], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'طلب غير صالح.',
                    'errors' => [],
                ], $e->getStatusCode());
            }

            Log::error($e->getMessage(), [
                'exception' => $e,
                'path' => $request->path(),
            ]);

            return response()->json([
                'message' => config('app.debug')
                    ? $e->getMessage()
                    : 'حدث خطأ غير متوقع، يرجى المحاولة لاحقاً.',
                'errors' => [],
            ], 500);
        });
    })->create();
