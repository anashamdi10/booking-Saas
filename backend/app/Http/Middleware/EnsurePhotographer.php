<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to photographer accounts.
 */
class EnsurePhotographer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'يجب تسجيل الدخول أولاً.'], 401);
        }

        if ($user->role !== UserRole::Photographer) {
            return response()->json(['message' => 'هذه الصفحة مخصصة للمصورين فقط.'], 403);
        }

        return $next($request);
    }
}
