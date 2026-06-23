<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'server_ip' => fake()->ipv4(),
            'server_domain' => fake()->unique()->domainName(),
            'server_name' => fake()->unique()->domainWord(),
        ];
    }
}
