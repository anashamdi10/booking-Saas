<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Jobs\RecordSiteVisit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a lightweight visit for the analytics dashboards.
 *
 * The write happens in a queued job so the public pages stay fast.
 */
class TrackSiteVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->method() === 'GET' && $response->getStatusCode() < 400) {
            RecordSiteVisit::dispatch(
                path: $request->path(),
                referer: $request->headers->get('referer'),
                photographerSlug: $request->route('slug'),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                sessionId: $request->header('X-Session-Id'),
            )->afterResponse();
        }

        return $response;
    }
}
