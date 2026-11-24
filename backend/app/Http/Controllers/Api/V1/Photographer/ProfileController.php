<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Photographer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Photographer\StoreWorkingHoursRequest;
use App\Http\Requests\Photographer\UpdateProfileRequest;
use App\Http\Resources\PhotographerResource;
use App\Models\WorkingHour;
use App\Services\StorageQuotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function __construct(private readonly StorageQuotaService $quota) {}

    /**
     * The photographer's own dashboard payload.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('profile', 'activeSubscription.plan');

        return response()->json([
            'data' => [
                'profile' => $user->profile === null ? null : new PhotographerResource($user->profile),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status->value,
                ],
                'subscription' => $user->activeSubscription?->load('plan'),
                'storage' => $this->quota->usageFor($user),
                'working_hours' => $user->workingHours()->orderBy('weekday')->get(),
            ],
        ]);
    }

    /**
     * Update the personal page (profile, contact details and theme).
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data): void {
            if (isset($data['name'])) {
                $user->name = $data['name'];
                $user->save();
            }

            unset($data['name']);

            if ($data === []) {
                return;
            }

            $profile = $user->profile;

            if ($profile === null) {
                $profile = $user->profile()->create(['slug' => 'p'.$user->id, 'country' => 'SA']);
            }

            if (isset($data['theme']) && is_array($data['theme'])) {
                $data['theme'] = array_merge($profile->theme ?? [], $data['theme']);
            }

            $profile->fill($data)->save();
        });

        return response()->json([
            'message' => 'تم تحديث بياناتك.',
            'data' => new PhotographerResource($user->profile()->first()),
        ]);
    }

    /**
     * Replace the photographer's weekly working hours.
     */
    public function updateWorkingHours(StoreWorkingHoursRequest $request): JsonResponse
    {
        $user = $request->user();
        $hours = $request->validated()['hours'];

        DB::transaction(function () use ($user, $hours): void {
            $user->workingHours()->delete();

            foreach ($hours as $hour) {
                WorkingHour::query()->create([
                    'photographer_id' => $user->id,
                    'weekday' => (int) $hour['weekday'],
                    'start_time' => $hour['start_time'],
                    'end_time' => $hour['end_time'],
                    'slot_interval_minutes' => (int) ($hour['slot_interval_minutes'] ?? 60),
                    'is_active' => (bool) ($hour['is_active'] ?? true),
                ]);
            }
        });

        return response()->json([
            'message' => 'تم حفظ مواعيد العمل.',
            'data' => $user->workingHours()->orderBy('weekday')->get(),
        ]);
    }
}
