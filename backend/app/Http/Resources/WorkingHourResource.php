<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WorkingHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkingHour
 */
class WorkingHourResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'weekday' => $this->weekday,
            'start_time' => substr((string) $this->start_time, 0, 5),
            'end_time' => substr((string) $this->end_time, 0, 5),
            'slot_interval_minutes' => $this->slot_interval_minutes,
            'is_active' => $this->is_active,
        ];
    }
}
