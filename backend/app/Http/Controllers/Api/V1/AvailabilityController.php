<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PhotographerProfile;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * Free slots of a photographer for the requested period.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'package_id' => ['nullable', 'integer'],
            'days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $profile = PhotographerProfile::query()
            ->with('user')
            ->where('slug', $slug)
            ->where('is_public', true)
            ->firstOrFail();

        $from = isset($filters['from'])
            ? CarbonImmutable::parse($filters['from'])->startOfDay()
            : CarbonImmutable::now()->startOfDay();

        $to = isset($filters['to'])
            ? CarbonImmutable::parse($filters['to'])->startOfDay()
            : $from->addDays((int) ($filters['days'] ?? 14));

        $package = null;

        if (! empty($filters['package_id'])) {
            $package = Package::query()
                ->where('photographer_id', $profile->user_id)
                ->where('is_active', true)
                ->findOrFail($filters['package_id']);
        }

        $slots = $this->availability->slotsFor($profile->user_id, $from, $to, $package);

        return response()->json([
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'duration_minutes' => $package?->duration_minutes ?? 60,
                'days' => $slots,
            ],
        ]);
    }
}
