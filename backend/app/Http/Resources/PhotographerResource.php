<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PhotographerProfile;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public representation of a photographer (listing card + profile header).
 *
 * @mixin PhotographerProfile
 */
class PhotographerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'id' => $user?->id,
            'slug' => $this->slug,
            'name' => $user?->name,
            'headline' => $this->headline,
            'bio' => $this->bio,
            'city' => $this->city,
            'country' => $this->country,
            'logo_url' => MediaUrl::make($this->logo_path),
            'cover_url' => MediaUrl::make($this->cover_path),
            'theme' => $this->themeColors(),
            'landing_template' => $this->landing_template?->value,
            'years_experience' => $this->years_experience,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,
            'is_featured' => $this->is_featured,
            'packages_count' => $this->packagesCount($user),
        ];
    }

    /**
     * Counts the photographer's active packages without triggering an N+1 query.
     */
    private function packagesCount(?User $user): int
    {
        if ($user === null) {
            return 0;
        }

        if ($user->relationLoaded('packages')) {
            return $user->packages->where('is_active', true)->count();
        }

        return $user->packages()->where('is_active', true)->count();
    }
}
