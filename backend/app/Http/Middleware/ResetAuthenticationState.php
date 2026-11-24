<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees that every request starts with a clean authentication state.
 *
 * Laravel resolves the auth guards once per process. Under php-fpm that is
 * harmless, but long running workers (Octane, RoadRunner) would keep the
 * previously authenticated user in memory and could serve one customer's data
 * to another. Dropping the guards before the request is handled makes the
 * behaviour identical on every runtime.
 */
class ResetAuthenticationState
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->app()->bound('auth')) {
            $this->app()->make('auth')->forgetGuards();
        }

        return $next($request);
    }

    private function app(): Container
    {
        return app();
    }
}
