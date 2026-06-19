<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        return [
            'name' => fake()->company(),
            'version' => fake()->semver(),
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
