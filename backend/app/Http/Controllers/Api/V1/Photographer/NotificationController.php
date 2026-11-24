<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Photographer;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Latest notifications of the authenticated photographer plus the number
     * still waiting to be read — one request feeds the whole bell.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest('created_at')
            ->take(20)
            ->get();

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'unread' => $user->notifications()->whereNull('read_at')->count(),
            ],
        ]);
    }

    /**
     * @param  string  $notification  UUID of the notification
     */
    public function read(Request $request, string $notification): JsonResponse
    {
        // Scoped to the caller: another photographer's id answers 404 instead
        // of confirming that the notification exists.
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return response()->json(['message' => 'تم التعليم كمقروء.']);
    }

    public function readAll(Request $request): JsonResponse
    {
        $updated = $request->user()
            ->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'تم تعليم كل الإشعارات كمقروءة.',
            'data' => ['updated' => $updated],
        ]);
    }
}
