<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\RequestLog;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestLog>
 */
class RequestLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'route' => fake()->randomElement(['/api/validate-license', '/api/check-update', '/api/download']),
            'method' => fake()->randomElement(['GET', 'POST']),
            'request' => [
                'domain' => fake()->domainName(),
                'license_key' => fake()->regexify('APICO-[A-Z0-9]{16}'),
            ],
            'status' => fake()->randomElement([200, 401, 403, 422]),
            'website_id' => Website::factory(),
            'license_id' => License::factory(),
        ];
    }
}
