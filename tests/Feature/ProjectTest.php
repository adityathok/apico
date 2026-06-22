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
        'slug',
        'version',
        'requires_wp',
        'requires_php',
        'plugin_wp_required',
        'github_url',
        'package_file',
        'package_external_url',
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
        'requires_wp' => '6.7',
        'requires_php' => '8.2',
        'plugin_wp_required' => null,
        'github_url' => 'https://github.com/example/client-child-theme',
        'package_file' => 'project-packages/client-child-theme/client-child-theme-v1-4-2.zip',
        'package_external_url' => 'https://example.com/downloads/client-child-theme.zip',
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
            ->where('projects.data.0.slug', $childProject->slug)
            ->where('projects.data.0.type', 'wp_theme_child')
            ->where('projects.data.0.requires', '6.7')
            ->where('projects.data.0.requires_php', '8.2')
            ->where('projects.data.0.plugin_wp_required', null)
            ->where('projects.data.0.parent.id', $parentProject->id)
            ->where('projects.data.0.parent.name', 'Core Theme')
            ->where('projects.data.0.package_file', 'project-packages/client-child-theme/client-child-theme-v1-4-2.zip')
            ->where('projects.data.0.package_external_url', 'https://example.com/downloads/client-child-theme.zip')
            ->has('parentProjects', 2)
            ->where('projects.meta.total', 2));
});

test('wordpress plugin project boolean field is exposed as a real boolean', function () {
    $user = User::factory()->create();
    $pluginProject = Project::factory()->create([
        'name' => 'Boolean Plugin',
        'slug' => 'boolean-plugin',
        'type' => 'wp_plugin',
        'plugin_wp_required' => true,
    ]);

    $this->actingAs($user)
        ->get(route('projects'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects')
            ->where('projects.data.0.id', $pluginProject->id)
            ->where('projects.data.0.plugin_wp_required', true));
});

test('authenticated users can create a project', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $parentProject = Project::factory()->create();
    $package = UploadedFile::fake()->create('velocity-addons.zip', 120, 'application/zip');

    $this->actingAs($user)
        ->post('/ajax/projects', [
            'name' => 'Velocity Addons',
            'slug' => 'Velocity Addons Terbaru',
            'version' => '2.1.0',
            'requires_wp' => '6.7',
            'requires_php' => '8.2',
            'plugin_wp_required' => true,
            'github_url' => 'https://github.com/example/velocity-addons',
            'package_external_url' => 'https://downloads.example.com/velocity-addons.zip',
            'description' => 'Plugin utama untuk klien.',
            'type' => 'wp_plugin',
            'parent_id' => $parentProject->id,
            'package_file' => $package,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Velocity Addons')
        ->assertJsonPath('data.slug', 'velocity-addons-terbaru')
        ->assertJsonPath('data.requires', '6.7')
        ->assertJsonPath('data.requires_php', '8.2')
        ->assertJsonPath('data.plugin_wp_required', true)
        ->assertJsonPath('data.type', 'wp_plugin')
        ->assertJsonPath('data.parent.id', $parentProject->id)
        ->assertJsonPath('data.parent.name', $parentProject->name);

    $this->assertDatabaseHas('projects', [
        'name' => 'Velocity Addons',
        'slug' => 'velocity-addons-terbaru',
        'requires_wp' => '6.7',
        'requires_php' => '8.2',
        'plugin_wp_required' => true,
        'type' => 'wp_plugin',
        'parent_id' => $parentProject->id,
        'package_external_url' => 'https://downloads.example.com/velocity-addons.zip',
    ]);

    $project = Project::where('name', 'Velocity Addons')->firstOrFail();

    expect($project->package_file)
        ->not->toBeNull()
        ->and($project->package_file)
        ->toStartWith('project-packages/')
        ->and($project->package_external_url)
        ->toBe('https://downloads.example.com/velocity-addons.zip');

    Storage::disk('public')->assertExists($project->package_file);
});

test('authenticated users can create a project with external package url only', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/ajax/projects', [
            'name' => 'Hosted Package Project',
            'slug' => 'Hosted Package Project',
            'type' => 'project_client',
            'package_external_url' => 'https://downloads.example.com/hosted-package-project.zip',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Hosted Package Project')
        ->assertJsonPath('data.slug', 'hosted-package-project')
        ->assertJsonPath('data.package_external_url', 'https://downloads.example.com/hosted-package-project.zip')
        ->assertJsonPath('data.package_file', null);

    $this->assertDatabaseHas('projects', [
        'name' => 'Hosted Package Project',
        'slug' => 'hosted-package-project',
        'type' => 'project_client',
        'package_external_url' => 'https://downloads.example.com/hosted-package-project.zip',
        'package_file' => null,
    ]);
});

test('authenticated users can update a project', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $oldParent = Project::factory()->create(['name' => 'Old Parent']);
    $newParent = Project::factory()->create(['name' => 'New Parent']);
    $project = Project::factory()->for($oldParent, 'parent')->create([
        'name' => 'Initial Project',
        'type' => 'project_internal',
        'package_file' => 'project-packages/old-package.zip',
        'package_external_url' => 'https://downloads.example.com/old-package.zip',
    ]);
    Storage::disk('public')->put('project-packages/old-package.zip', 'old package');
    $replacementPackage = UploadedFile::fake()->create('updated-project.zip', 240, 'application/zip');

    $this->actingAs($user)
        ->patch("/ajax/projects/{$project->id}", [
            'name' => 'Updated Project',
            'slug' => 'Updated Project Premium',
            'type' => 'project_client',
            'parent_id' => $newParent->id,
            'version' => '3.0.0',
            'requires_wp' => '6.7',
            'requires_php' => '8.2',
            'plugin_wp_required' => true,
            'package_external_url' => 'https://downloads.example.com/updated-project.zip',
            'package_file' => $replacementPackage,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Project')
        ->assertJsonPath('data.slug', 'updated-project-premium')
        ->assertJsonPath('data.requires', null)
        ->assertJsonPath('data.requires_php', null)
        ->assertJsonPath('data.plugin_wp_required', null)
        ->assertJsonPath('data.type', 'project_client')
        ->assertJsonPath('data.version', '3.0.0')
        ->assertJsonPath('data.parent.id', $newParent->id);

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'name' => 'Updated Project',
        'slug' => 'updated-project-premium',
        'requires_wp' => null,
        'requires_php' => null,
        'plugin_wp_required' => null,
        'type' => 'project_client',
        'parent_id' => $newParent->id,
        'version' => '3.0.0',
        'package_external_url' => 'https://downloads.example.com/updated-project.zip',
    ]);

    $project->refresh();

    expect($project->package_file)
        ->not->toBeNull()
        ->and($project->package_file)
        ->toStartWith('project-packages/')
        ->and($project->package_external_url)
        ->toBe('https://downloads.example.com/updated-project.zip');

    Storage::disk('public')->assertMissing('project-packages/old-package.zip');
    Storage::disk('public')->assertExists($project->package_file);
});

test('authenticated users can remove an existing package file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $project = Project::factory()->create([
        'name' => 'Packaged Project',
        'type' => 'project_client',
        'package_file' => 'project-packages/packaged-project/packaged-project-v1-0-0.zip',
        'package_external_url' => null,
    ]);
    Storage::disk('public')->put($project->package_file, 'existing package');

    $this->actingAs($user)
        ->patch("/ajax/projects/{$project->id}", [
            'name' => 'Packaged Project',
            'slug' => 'packaged-project',
            'type' => 'project_client',
            'remove_package_file' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Packaged Project')
        ->assertJsonPath('data.package_file', null)
        ->assertJsonPath('data.package_file_url', null);

    $project->refresh();

    expect($project->package_file)->toBeNull();

    Storage::disk('public')->assertMissing('project-packages/packaged-project/packaged-project-v1-0-0.zip');
});

test('project slug is normalized before saving', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $package = UploadedFile::fake()->create('project-slug.zip', 120, 'application/zip');

    $this->actingAs($user)
        ->post('/ajax/projects', [
            'name' => 'Project Slug',
            'slug' => 'Slug Campur Spasi & Simbol',
            'type' => 'project_internal',
            'package_file' => $package,
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'slug-campur-spasi-simbol');

    $this->assertDatabaseHas('projects', [
        'name' => 'Project Slug',
        'slug' => 'slug-campur-spasi-simbol',
    ]);
});
