<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks suspended or unapproved accounts from using the authenticated API.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'يجب تسجيل الدخول أولاً.'], 401);
        }

        if ($user->status === UserStatus::Suspended) {
            $token = $user->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return response()->json(['message' => 'تم إيقاف حسابك، يرجى التواصل مع الدعم.'], 403);
        }

        if ($user->status === UserStatus::Pending) {
            return response()->json([
                'message' => 'حسابك قيد المراجعة، سيتم تفعيله بعد موافقة الإدارة.',
            ], 403);
        }

        return $next($request);
    }
}
