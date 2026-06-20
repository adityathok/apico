<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'version' => fake()->semver(),
            'requires_wp' => fake()->optional()->semver(),
            'requires_php' => fake()->optional()->randomElement(['8.1', '8.2', '8.3', '8.4']),
            'github_url' => fake()->optional()->url(),
            'package_file' => fake()->optional()->filePath(),
            'package_external_url' => fake()->optional()->url(),
            'description' => fake()->optional()->sentence(),
            'type' => fake()->randomElement([
                'project_internal',
                'project_client',
                'wp_theme',
                'wp_plugin',
                'wp_theme_child',
            ]),
            'parent_id' => null,
        ];
    }
}
