<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PhotographerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PhotographerProfile>
 */
class PhotographerProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'user_id' => User::factory(),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'headline' => fake()->sentence(4),
            'bio' => fake()->paragraph(),
            'city' => fake()->randomElement(['الرياض', 'جدة', 'الدمام', 'مكة', 'المدينة', 'أبها']),
            'country' => 'SA',
            'phone' => fake()->numerify('+9665########'),
            'whatsapp' => fake()->numerify('9665########'),
            'years_experience' => fake()->numberBetween(1, 20),
            'rating_avg' => fake()->randomFloat(1, 3.5, 5.0),
            'rating_count' => fake()->numberBetween(0, 200),
            'is_public' => true,
            'is_featured' => false,
            'published_at' => now(),
        ];
    }
}
