<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Package
 */
class PackageResource extends JsonResource
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
            'duration_minutes' => $this->duration_minutes,
            'max_photos' => $this->max_photos,
            'deliverables' => $this->deliverables ?? [],
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
