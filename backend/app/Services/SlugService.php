<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates unique, URL friendly slugs across tables.
 */
class SlugService
{
    /**
     * @param  callable(string): bool  $exists
     */
    public function unique(string $source, string $table, callable $exists): string
    {
        $base = Str::slug($source);

        if ($base === '') {
            $base = 'item';
        }

        $slug = $base;
        $suffix = 1;

        while ($exists($slug)) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    public function forPhotographer(string $name, ?int $ignoreUserId = null): string
    {
        return $this->unique($name, 'photographer_profiles', function (string $slug) use ($ignoreUserId): bool {
            return DB::table('photographer_profiles')
                ->where('slug', $slug)
                ->when($ignoreUserId !== null, fn ($query) => $query->where('user_id', '!=', $ignoreUserId))
                ->exists();
        });
    }

    public function forPackage(int $photographerId, string $name): string
    {
        return $this->unique($name, 'packages', function (string $slug) use ($photographerId): bool {
            return DB::table('packages')
                ->where('photographer_id', $photographerId)
                ->where('slug', $slug)
                ->exists();
        });
    }

    public function forGallery(int $photographerId, string $title): string
    {
        return $this->unique($title, 'galleries', function (string $slug) use ($photographerId): bool {
            return DB::table('galleries')
                ->where('photographer_id', $photographerId)
                ->where('slug', $slug)
                ->exists();
        });
    }

    public function forPlan(string $name, ?int $ignorePlanId = null): string
    {
        // Arabic names slug to nothing, so plans get their own base instead
        // of the generic fallback the shared helper uses.
        $source = Str::slug($name) === '' ? 'plan '.$name : $name;

        return $this->unique($source, 'plans', function (string $slug) use ($ignorePlanId): bool {
            return DB::table('plans')
                ->where('slug', $slug)
                ->when($ignorePlanId !== null, fn ($query) => $query->where('id', '!=', $ignorePlanId))
                ->exists();
        });
    }
}
