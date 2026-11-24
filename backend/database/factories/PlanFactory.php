<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gigabytes = fake()->numberBetween(5, 200);

        return [
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(10),
            'price' => fake()->randomFloat(2, 49, 999),
            'currency' => 'SAR',
            'storage_limit_bytes' => $gigabytes * 1024 ** 3,
            'max_galleries' => fake()->numberBetween(5, 100),
            'max_clients' => fake()->numberBetween(10, 500),
            'features' => ['دعم فني', 'صفحة شخصية مخصصة'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
