<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Photographer;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly StatsService $stats) {}

    /**
     * Aggregated numbers shown on the photographer's dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = min(90, max(7, (int) $request->query('days', 30)));

        return response()->json([
            'data' => [
                'stats' => $this->stats->photographerStats($user),
                'series' => $this->stats->dailySeries($user, $days),
            ],
        ]);
    }
}
