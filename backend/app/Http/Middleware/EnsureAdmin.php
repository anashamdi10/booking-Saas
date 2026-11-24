<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to administrator accounts.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'يجب تسجيل الدخول أولاً.'], 401);
        }

        if ($user->role !== UserRole::Admin) {
            return response()->json(['message' => 'لا تملك صلاحية الوصول لهذه العملية.'], 403);
        }

        return $next($request);
    }
}
