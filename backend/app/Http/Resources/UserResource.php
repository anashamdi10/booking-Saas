<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role instanceof UserRole ? $this->role->value : $this->role,
            'status' => $this->status instanceof UserStatus ? $this->status->value : $this->status,
            'avatar_url' => MediaUrl::make($this->avatar_path),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
