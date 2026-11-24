<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+30 days');

        return [
            'reference' => Booking::generateReference(),
            'photographer_id' => User::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->numerify('+9665########'),
            'start_at' => $start,
            'end_at' => (clone $start)->modify('+60 minutes'),
            'status' => BookingStatus::Pending,
            'source' => BookingSource::Website,
            'notes' => fake()->sentence(8),
            'currency' => 'SAR',
        ];
    }
}
