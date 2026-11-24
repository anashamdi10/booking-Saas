<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Services\SlugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlanController extends Controller
{
    public function __construct(private readonly SlugService $slugs) {}

    /**
     * Every plan is listed — active or not — because the management screen
     * must be able to switch a plan off without losing it. Screens that only
     * offer plans for assignment filter on `is_active` themselves.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $request->integer('per_page', 50)));

        $plans = Plan::query()
            ->withCount('subscriptions')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);

        return PlanResource::collection($plans)->response();
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = Plan::query()->create($this->payload($request));

        return (new PlanResource($plan->loadCount('subscriptions')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(StorePlanRequest $request, Plan $plan): JsonResponse
    {
        $plan->update($this->payload($request, $plan));

        return (new PlanResource($plan->fresh()->loadCount('subscriptions')))->response();
    }

    /**
     * Subscriptions reference the plan (restrictOnDelete), so a plan that is
     * already in use cannot be deleted — the admin switches it off instead
     * and the history stays intact.
     */
    public function destroy(Plan $plan): JsonResponse
    {
        if ($plan->subscriptions()->exists()) {
            return response()->json([
                'message' => 'هذه الباقة مرتبطة باشتراكات قائمة، فلا يمكن حذفها — أوقفها بدل الحذف.',
            ], 422);
        }

        $plan->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StorePlanRequest $request, ?Plan $plan = null): array
    {
        $data = $request->validated();

        // The admin works in gigabytes; the column stores bytes.
        $data['storage_limit_bytes'] = (int) round($data['storage_limit_gb'] * 1024 ** 3);
        unset($data['storage_limit_gb']);

        // Renaming regenerates the slug so it keeps deriving from the name.
        if (isset($data['name']) && $data['name'] !== $plan?->name) {
            $data['slug'] = $this->slugs->forPlan($data['name'], $plan?->id);
        }

        return $data;
    }
}
