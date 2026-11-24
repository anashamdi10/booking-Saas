<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePhotographerRequest;
use App\Http\Resources\UserResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PhotographerController extends Controller
{
    /**
     * All photographer accounts with their subscription state.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:'.implode(',', array_column(UserStatus::cases(), 'value'))],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $query = User::query()
            ->with(['profile', 'activeSubscription.plan'])
            ->where('role', UserRole::Photographer)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['q'] ?? null, function ($q, $term): void {
                $term = '%'.$term.'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->latest('id');

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 20));

        return response()->json([
            'data' => collect($paginator->items())->map(fn (User $user) => [
                'user' => new UserResource($user),
                'profile' => $user->profile,
                'subscription' => $user->activeSubscription?->load('plan'),
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Approve / suspend a photographer and manage the subscription plan.
     */
    public function update(UpdatePhotographerRequest $request, User $photographer): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($photographer, $data, $request): void {
            if (isset($data['status'])) {
                $photographer->status = UserStatus::from($data['status']);
                $photographer->save();

                if ($photographer->status === UserStatus::Active && $photographer->profile !== null) {
                    $photographer->profile->update(['published_at' => now()]);
                }
            }

            if (array_key_exists('plan_id', $data) && $data['plan_id'] !== null) {
                $plan = Plan::query()->findOrFail($data['plan_id']);

                $subscription = $photographer->subscriptions()
                    ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::Pending->value])
                    ->latest('id')
                    ->first();

                if ($subscription === null) {
                    $subscription = $photographer->subscriptions()->create([
                        'plan_id' => $plan->id,
                        'status' => SubscriptionStatus::Pending,
                    ]);
                }

                $subscription->plan_id = $plan->id;
                $subscription->storage_limit_bytes = $data['storage_limit_bytes'] ?? $plan->storage_limit_bytes;
                $subscription->starts_at = $data['starts_at'] ?? now();
                $subscription->ends_at = $data['ends_at'] ?? null;
                $subscription->admin_notes = $data['admin_notes'] ?? $subscription->admin_notes;

                if (isset($data['subscription_status'])) {
                    $subscription->status = SubscriptionStatus::from($data['subscription_status']);
                }

                if ($subscription->status === SubscriptionStatus::Active) {
                    $subscription->activated_at = now();
                    $subscription->activated_by = $request->user()->id;
                }

                $subscription->save();
            }
        });

        return response()->json([
            'message' => 'تم تحديث بيانات المصور.',
            'data' => [
                'user' => new UserResource($photographer->refresh()),
                'subscription' => $photographer->activeSubscription?->load('plan'),
            ],
        ]);
    }
}
