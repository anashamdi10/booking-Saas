<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'photographer_id' => $this->photographer_id,
            'photographer_name' => $this->photographer?->name,
            'package' => $this->whenLoaded('package', fn () => $this->package === null ? null : [
                'id' => $this->package->id,
                'name' => $this->package->name,
                'duration_minutes' => $this->package->duration_minutes,
            ]),
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'start_at' => $this->start_at?->toIso8601String(),
            'end_at' => $this->end_at?->toIso8601String(),
            'status' => $this->status instanceof BookingStatus ? $this->status->value : $this->status,
            'status_label' => $this->status instanceof BookingStatus ? $this->status->label() : null,
            'source' => $this->source?->value,
            'notes' => $this->notes,
            'total_price' => $this->total_price !== null ? (float) $this->total_price : null,
            'currency' => $this->currency,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
