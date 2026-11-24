<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->data ?? [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $payload['title'] ?? '',
            'reference' => $payload['reference'] ?? null,
            'customer_name' => $payload['customer_name'] ?? null,
            'start_at' => $payload['start_at'] ?? null,
            'package_name' => $payload['package_name'] ?? null,
            'url' => $payload['url'] ?? null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
