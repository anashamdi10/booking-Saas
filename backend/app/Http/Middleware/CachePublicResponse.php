<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a public, user independent read endpoint as cacheable.
 *
 * This must only be used on endpoints whose response is identical for every
 * visitor — never on anything that depends on the authenticated user.
 */
class CachePublicResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->isSuccessful()) {
            $response->headers->set('Cache-Control', 'public, max-age=60, s-maxage=300');
            $response->headers->set('Vary', 'Accept-Encoding');
        }

        return $response;
    }
}
