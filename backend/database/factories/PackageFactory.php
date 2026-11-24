<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'photographer_id' => User::factory(),
            'name' => fake()->randomElement(['جلسة تصوير', 'تغطية مناسبة', 'تصوير منتجات', 'باقة العائلات']),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(10),
            'price' => fake()->randomFloat(2, 100, 2500),
            'currency' => 'SAR',
            'duration_minutes' => fake()->randomElement([30, 60, 90, 120]),
            'max_photos' => fake()->numberBetween(20, 200),
            'deliverables' => ['صور عالية الدقة', 'ألبوم رقمي'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
