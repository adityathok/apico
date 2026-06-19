<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
            ->has('parentProjects', 2)
            ->where('projects.meta.total', 2));
});

test('authenticated users can create a project', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $parentProject = Project::factory()->create();
    $package = UploadedFile::fake()->create('velocity-addons.zip', 120, 'application/zip');

    $this->actingAs($user)
        ->post('/ajax/projects', [
            'name' => 'Velocity Addons',
            'version' => '2.1.0',
            'github_url' => 'https://github.com/example/velocity-addons',
            'description' => 'Plugin utama untuk klien.',
            'type' => 'wp_plugin',
            'parent_id' => $parentProject->id,
            'package_file' => $package,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Velocity Addons')
        ->assertJsonPath('data.type', 'wp_plugin')
        ->assertJsonPath('data.parent.id', $parentProject->id)
        ->assertJsonPath('data.parent.name', $parentProject->name);

    $this->assertDatabaseHas('projects', [
        'name' => 'Velocity Addons',
        'type' => 'wp_plugin',
        'parent_id' => $parentProject->id,
    ]);

    $project = Project::where('name', 'Velocity Addons')->firstOrFail();

    expect($project->package_file_url)
        ->not->toBeNull()
        ->and($project->package_file_url)
        ->toContain('/storage/project-packages/');

    $storedPath = preg_replace(
        '#^/storage/#',
        '',
        (string) parse_url($project->package_file_url, PHP_URL_PATH),
    );

    expect($storedPath)->toBeString();
    Storage::disk('public')->assertExists($storedPath);
});

test('authenticated users can update a project', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $oldParent = Project::factory()->create(['name' => 'Old Parent']);
    $newParent = Project::factory()->create(['name' => 'New Parent']);
    $project = Project::factory()->for($oldParent, 'parent')->create([
        'name' => 'Initial Project',
        'type' => 'project_internal',
        'package_file_url' => '/storage/project-packages/old-package.zip',
    ]);
    Storage::disk('public')->put('project-packages/old-package.zip', 'old package');
    $replacementPackage = UploadedFile::fake()->create('updated-project.zip', 240, 'application/zip');

    $this->actingAs($user)
        ->patch("/ajax/projects/{$project->id}", [
            'name' => 'Updated Project',
            'type' => 'project_client',
            'parent_id' => $newParent->id,
            'version' => '3.0.0',
            'package_file' => $replacementPackage,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Project')
        ->assertJsonPath('data.type', 'project_client')
        ->assertJsonPath('data.version', '3.0.0')
        ->assertJsonPath('data.parent.id', $newParent->id);

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => 'Updated Project',
        'type' => 'project_client',
        'parent_id' => $newParent->id,
        'version' => '3.0.0',
    ]);

    $project->refresh();

    expect($project->package_file_url)
        ->not->toBeNull()
        ->and($project->package_file_url)
        ->toContain('/storage/project-packages/');

    Storage::disk('public')->assertMissing('project-packages/old-package.zip');

    $storedPath = preg_replace(
        '#^/storage/#',
        '',
        (string) parse_url($project->package_file_url, PHP_URL_PATH),
    );

    expect($storedPath)->toBeString();
    Storage::disk('public')->assertExists($storedPath);
});
