<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'currency' => $this->currency,
            'storage_limit_bytes' => $this->storage_limit_bytes,
            'storage_limit_gb' => $this->storageLimitGb(),
            'max_galleries' => $this->max_galleries,
            'max_clients' => $this->max_clients,
            'max_bookings_per_month' => $this->max_bookings_per_month,
            'features' => $this->features ?? [],
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,

            // `withCount` puts the number on the model as a plain attribute
            // rather than a loaded relation, so it is only exposed when the
            // caller asked for it — the admin screen uses it to refuse
            // deleting a plan that subscriptions still point at.
            'subscriptions_count' => $this->when(
                array_key_exists('subscriptions_count', $this->resource->getAttributes()),
                $this->resource->subscriptions_count,
            ),
        ];
    }
}
