<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

test('projects table has the expected columns', function () {
    expect(Schema::hasColumns('projects', [
        'id',
        'name',
        'version',
        'github_url',
        'package_file_url',
        'description',
        'type',
        'parent_id',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('a project can belong to a parent project and have child projects', function () {
    $parentProject = Project::factory()->create([
        'type' => 'wp_theme',
    ]);
    $childProject = Project::factory()->for($parentProject, 'parent')->create([
        'type' => 'wp_theme_child',
    ]);

    expect($childProject->parent)
        ->toBeInstanceOf(Project::class)
        ->id->toBe($parentProject->id)
        ->and($parentProject->children->first())
        ->toBeInstanceOf(Project::class)
        ->id->toBe($childProject->id);
});

test('a project can be created with the supported types', function () {
    $supportedTypes = [
        'project_internal',
        'project_client',
        'wp_theme',
        'wp_plugin',
        'wp_theme_child',
    ];

    foreach ($supportedTypes as $type) {
        $project = Project::factory()->create([
            'type' => $type,
        ]);

        expect($project->type)->toBe($type);
    }
});

test('authenticated users can view projects from the controller', function () {
    $user = User::factory()->create();
    $parentProject = Project::factory()->create([
        'name' => 'Core Theme',
        'type' => 'wp_theme',
    ]);
    $childProject = Project::factory()->for($parentProject, 'parent')->create([
        'name' => 'Client Child Theme',
        'type' => 'wp_theme_child',
        'version' => '1.4.2',
        'github_url' => 'https://github.com/example/client-child-theme',
        'package_file_url' => 'https://example.com/downloads/client-child-theme.zip',
        'description' => 'Child theme for a client project.',
    ]);

    $this->actingAs($user)
        ->get(route('projects'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects')
            ->has('projects.data', 2)
            ->where('projects.data.0.id', $childProject->id)
            ->where('projects.data.0.name', 'Client Child Theme')
            ->where('projects.data.0.type', 'wp_theme_child')
            ->where('projects.data.0.parent.id', $parentProject->id)
            ->where('projects.data.0.parent.name', 'Core Theme')
            ->where('projects.meta.total', 2));
});
