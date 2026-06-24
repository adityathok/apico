<?php

use App\Models\Project;
use App\Models\ProjectChangelog;
use App\Models\User;
use Database\Seeders\ProjectChangelogSeeder;
use Illuminate\Support\Facades\Schema;

test('project changelogs table has the expected columns', function () {
    expect(Schema::hasColumns('project_changelogs', [
        'id',
        'project_id',
        'project_version',
        'changelog_content',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('a project changelog belongs to a project', function () {
    $project = Project::factory()->create();
    $projectChangelog = ProjectChangelog::factory()->for($project)->create();

    expect($projectChangelog->project)
        ->toBeInstanceOf(Project::class)
        ->id->toBe($project->id)
        ->and($project->changelogs->first())
        ->toBeInstanceOf(ProjectChangelog::class)
        ->id->toBe($projectChangelog->id);
});

test('project changelog seeder creates records once', function () {
    $this->seed(ProjectChangelogSeeder::class);
    $this->seed(ProjectChangelogSeeder::class);

    expect(Project::whereIn('slug', [
        'velocity-core',
        'velocity-theme',
        'velocity-addons',
    ])->count())->toBe(3)
        ->and(ProjectChangelog::count())->toBe(3)
        ->and(ProjectChangelog::where('project_version', '1.0.0')->exists())->toBeTrue()
        ->and(ProjectChangelog::where('project_version', '2.3.0')->exists())->toBeTrue()
        ->and(ProjectChangelog::where('project_version', '3.1.0')->exists())->toBeTrue();
});

test('authenticated users can view project changelogs from the controller', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'name' => 'Velocity Addons',
        'slug' => 'velocity-addons',
    ]);
    $projectChangelog = ProjectChangelog::factory()->for($project)->create([
        'project_version' => '3.1.0',
        'changelog_content' => 'Added shortcode settings and export compatibility fixes.',
    ]);

    $this->actingAs($user)
        ->get('/ajax/project-changelogs')
        ->assertOk()
        ->assertJsonPath('data.0.id', $projectChangelog->id)
        ->assertJsonPath('data.0.project_version', '3.1.0')
        ->assertJsonPath('data.0.changelog_content', 'Added shortcode settings and export compatibility fixes.')
        ->assertJsonPath('data.0.project.id', $project->id)
        ->assertJsonPath('data.0.project.name', 'Velocity Addons')
        ->assertJsonPath('meta.total', 1);
});

test('authenticated users can filter project changelogs by project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $projectChangelog = ProjectChangelog::factory()->for($project)->create([
        'project_version' => '2.2.0',
    ]);
    ProjectChangelog::factory()->for($otherProject)->create([
        'project_version' => '9.9.9',
    ]);

    $this->actingAs($user)
        ->get("/ajax/project-changelogs?project_id={$project->id}")
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $projectChangelog->id)
        ->assertJsonPath('data.0.project_id', $project->id)
        ->assertJsonMissingPath('data.1');
});

test('authenticated users can create a project changelog', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();

    $this->actingAs($user)
        ->post('/ajax/project-changelogs', [
            'project_id' => $project->id,
            'project_version' => '2.0.0',
            'changelog_content' => 'Initial stable public release.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.project_id', $project->id)
        ->assertJsonPath('data.project_version', '2.0.0')
        ->assertJsonPath('data.changelog_content', 'Initial stable public release.')
        ->assertJsonPath('data.project.id', $project->id);

    $this->assertDatabaseHas('project_changelogs', [
        'project_id' => $project->id,
        'project_version' => '2.0.0',
        'changelog_content' => 'Initial stable public release.',
    ]);
});

test('authenticated users can update and delete a project changelog', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $updatedProject = Project::factory()->create();
    $projectChangelog = ProjectChangelog::factory()->for($project)->create([
        'project_version' => '1.0.0',
        'changelog_content' => 'Initial release.',
    ]);

    $this->actingAs($user)
        ->patch("/ajax/project-changelogs/{$projectChangelog->id}", [
            'project_id' => $updatedProject->id,
            'project_version' => '1.1.0',
            'changelog_content' => 'Improved onboarding flow and fixed version checks.',
        ])
        ->assertOk()
        ->assertJsonPath('data.project_id', $updatedProject->id)
        ->assertJsonPath('data.project_version', '1.1.0')
        ->assertJsonPath('data.project.name', $updatedProject->name);

    $this->assertDatabaseHas('project_changelogs', [
        'id' => $projectChangelog->id,
        'project_id' => $updatedProject->id,
        'project_version' => '1.1.0',
        'changelog_content' => 'Improved onboarding flow and fixed version checks.',
    ]);

    $this->actingAs($user)
        ->delete("/ajax/project-changelogs/{$projectChangelog->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('project_changelogs', [
        'id' => $projectChangelog->id,
    ]);
});
