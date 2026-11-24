<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;

class SiteController extends Controller
{
    public function __construct(private readonly StatsService $stats) {}

    /**
     * Public counters shown on the landing page.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => $this->stats->siteStats(),
        ]);
    }
}
