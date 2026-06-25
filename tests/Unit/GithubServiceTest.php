<?php

use App\Models\Project;
use App\Services\GithubService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('github service syncs the latest release into the project version and package url', function () {
    $project = Project::factory()->create([
        'github_url' => 'https://github.com/example/velocity-addons',
        'version' => '1.0.0',
        'package_external_url' => null,
    ]);

    Http::fake([
        'https://api.github.com/repos/example/velocity-addons/releases/latest' => Http::response([
            'tag_name' => '2.4.0',
            'name' => 'Velocity Addons 2.4.0',
            'published_at' => '2026-06-25T08:00:00Z',
            'zipball_url' => 'https://api.github.com/repos/example/velocity-addons/zipball/2.4.0',
            'assets' => [
                [
                    'name' => 'velocity-addons.zip',
                    'size' => 1200,
                    'browser_download_url' => 'https://github.com/example/velocity-addons/releases/download/2.4.0/velocity-addons.zip',
                ],
            ],
        ], 200),
    ]);

    $syncedProject = app(GithubService::class)->syncGithubProjectRelease($project->id);

    expect($syncedProject)->not->toBeNull();

    $project->refresh();

    expect($project->version)->toBe('2.4.0')
        ->and($project->package_external_url)->toBe('https://github.com/example/velocity-addons/releases/download/2.4.0/velocity-addons.zip');
});

test('github service does not sync a project without github url', function () {
    $project = Project::factory()->create([
        'github_url' => null,
        'version' => '1.0.0',
        'package_external_url' => 'https://downloads.example.com/original.zip',
    ]);

    Http::fake();

    $syncedProject = app(GithubService::class)->syncGithubProjectRelease($project->id);

    expect($syncedProject)->toBeNull();

    Http::assertNothingSent();

    $project->refresh();

    expect($project->version)->toBe('1.0.0')
        ->and($project->package_external_url)->toBe('https://downloads.example.com/original.zip');
});

test('github service keeps the project unchanged when latest release cannot be fetched', function () {
    $project = Project::factory()->create([
        'github_url' => 'https://github.com/example/velocity-addons',
        'version' => '1.0.0',
        'package_external_url' => 'https://downloads.example.com/original.zip',
    ]);

    Http::fake([
        'https://api.github.com/repos/example/velocity-addons/releases/latest' => Http::response([], 404),
    ]);

    $syncedProject = app(GithubService::class)->syncGithubProjectRelease($project->id);

    expect($syncedProject)->toBeNull();

    $project->refresh();

    expect($project->version)->toBe('1.0.0')
        ->and($project->package_external_url)->toBe('https://downloads.example.com/original.zip');
});
