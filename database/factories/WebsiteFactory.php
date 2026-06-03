<?php

namespace Database\Factories;

use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Website>
 */
class WebsiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain' => fake()->unique()->domainName(),
            'ip_address' => fake()->ipv4(),
            'license_key' => fake()->unique()->regexify('APICO-[A-Z0-9]{16}'),
            'status' => fake()->randomElement(['active', 'invalid']),
            'theme_version' => fake()->semver(),
            'plugin_version' => fake()->semver(),
            'wp_version' => fake()->semver(),
            'php_version' => fake()->randomElement(['8.2', '8.3', '8.4']),
        ];
    }
}
