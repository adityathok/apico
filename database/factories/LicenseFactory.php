<?php

namespace Database\Factories;

use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'code' => Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4),
            'is_active' => true,
            'used_at' => null,
            'expires_at' => fake()->optional()->dateTimeBetween('now', '+1 year'),
        ];
    }
}
