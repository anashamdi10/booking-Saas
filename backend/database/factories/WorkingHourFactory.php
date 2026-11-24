<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkingHour>
 */
class WorkingHourFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'photographer_id' => User::factory(),
            'weekday' => fake()->numberBetween(0, 6),
            'start_time' => '16:00:00',
            'end_time' => '22:00:00',
            'slot_interval_minutes' => 60,
            'is_active' => true,
        ];
    }
}
