<?php

use App\Models\Category;
use App\Models\License;
use App\Models\Post;
use App\Models\Project;
use App\Models\RequestLog;
use App\Models\Tag;
use App\Models\User;
use App\Models\Website;
use App\Services\GithubService;
use App\Services\UnsplashService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $license = License::factory()->create([
        'code' => 'APICO-API-TEST',
    ]);

    $this->actingAs(User::factory()->create());
    $this->withHeader('License', $license->code);
});

test('post controller stores a post with category and tag pivots', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();
    $image = UploadedFile::fake()->image('api.jpg');

    $response = $this->post('/ajax/posts', [
        'user_id' => $user->id,
        'title' => 'Getting Started With APIs',
        'slug' => 'getting-started-with-apis',
        'image' => $image,
        'image_caption' => 'API article cover image.',
        'excerpt' => 'A short API intro.',
        'content' => 'Complete API article content.',
        'published_at' => now()->toDateTimeString(),
        'category_ids' => [$category->id],
        'tag_ids' => [$tag->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Getting Started With APIs')
        ->assertJsonPath('data.image_caption', 'API article cover image.')
        ->assertJsonPath('data.categories.0.id', $category->id)
        ->assertJsonPath('data.tags.0.id', $tag->id);

    $post = Post::where('slug', 'getting-started-with-apis')->firstOrFail();

    expect($post->categories()->pluck('categories.id')->all())->toBe([$category->id])
        ->and($post->tags()->pluck('tags.id')->all())->toBe([$tag->id])
        ->and($post->image)->toStartWith('post/'.now()->format('y-m').'/');

    Storage::disk('public')->assertExists($post->image);
});

test('post controller updates and deletes a post', function () {
    Storage::fake('public');

    $oldImage = 'post/'.now()->format('y-m').'/old.jpg';
    Storage::disk('public')->put($oldImage, 'old image');

    $post = Post::factory()->create(['image' => $oldImage]);
    $category = Category::factory()->create();
    $newImage = UploadedFile::fake()->image('updated.jpg');

    $this->patch("/ajax/posts/{$post->id}", [
        'title' => 'Updated Post Title',
        'image_caption' => 'Updated cover caption.',
        'image' => $newImage,
        'category_ids' => [$category->id],
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated Post Title')
        ->assertJsonPath('data.image_caption', 'Updated cover caption.')
        ->assertJsonPath('data.categories.0.id', $category->id);

    $post->refresh();

    Storage::disk('public')->assertMissing($oldImage);
    Storage::disk('public')->assertExists($post->image);

    $this->deleteJson("/ajax/posts/{$post->id}")->assertNoContent();

    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    Storage::disk('public')->assertMissing($post->image);
});

test('post controller returns recommended unsplash images', function () {
    $unsplashService = Mockery::mock(UnsplashService::class);
    $unsplashService->shouldReceive('searchPhotos')
        ->once()
        ->with('laravel api', 2, 9, 'portrait')
        ->andReturn([
            'total' => 18,
            'total_pages' => 2,
            'results' => [
                [
                    'id' => 'photo-1',
                    'description' => 'Laravel workspace',
                    'urls' => [
                        'thumb' => 'https://images.unsplash.com/photo-1-thumb',
                        'small' => 'https://images.unsplash.com/photo-1-small',
                        'regular' => 'https://images.unsplash.com/photo-1-regular',
                    ],
                    'user' => [
                        'name' => 'Taylor',
                    ],
                ],
            ],
        ]);

    $this->app->instance(UnsplashService::class, $unsplashService);

    $this->getJson('/ajax/posts/recommended-images?query=laravel%20api&page=2&per_page=9&orientation=portrait')
        ->assertOk()
        ->assertJsonPath('data.0.id', 'photo-1')
        ->assertJsonPath('data.0.description', 'Laravel workspace')
        ->assertJsonPath('data.0.thumb_url', 'https://images.unsplash.com/photo-1-thumb')
        ->assertJsonPath('data.0.small_url', 'https://images.unsplash.com/photo-1-small')
        ->assertJsonPath('data.0.regular_url', 'https://images.unsplash.com/photo-1-regular')
        ->assertJsonPath('data.0.download_url', 'https://images.unsplash.com/photo-1-regular?w=640&fit=max&auto=format&q=80')
        ->assertJsonPath('data.0.author_name', 'Taylor')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 9)
        ->assertJsonPath('meta.total', 18)
        ->assertJsonPath('meta.from', 10)
        ->assertJsonPath('meta.to', 10);
});

test('post controller returns selected unsplash image as a downloadable file', function () {
    Http::fake([
        'images.unsplash.com/*' => Http::response('image-binary', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    $response = $this->postJson('/ajax/posts/recommended-image', [
        'url' => 'https://images.unsplash.com/photo-123?fit=crop&w=1200',
        'file_name' => 'Laravel Workspace',
    ]);

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('Content-Disposition', 'inline; filename="laravel-workspace.jpg"');

    expect($response->getContent())->toBe('image-binary');
});

test('category controller stores updates and deletes a category', function () {
    $post = Post::factory()->create();

    $response = $this->postJson('/ajax/categories', [
        'name' => 'News',
        'slug' => 'news',
        'description' => 'Fresh updates.',
        'post_ids' => [$post->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'News')
        ->assertJsonPath('data.posts.0.id', $post->id);

    $category = Category::where('slug', 'news')->firstOrFail();

    $this->patchJson("/ajax/categories/{$category->id}", [
        'name' => 'Product News',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Product News');

    $this->deleteJson("/ajax/categories/{$category->id}")->assertNoContent();
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('category controller normalizes slug before saving', function () {
    $response = $this->postJson('/ajax/categories', [
        'name' => 'Berita Utama',
        'slug' => 'Slug Campur Spasi & Simbol',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.slug', 'slug-campur-spasi-simbol');

    $category = Category::where('slug', 'slug-campur-spasi-simbol')->firstOrFail();

    expect($category->name)->toBe('Berita Utama');
});

test('category controller normalizes slug before updating', function () {
    $category = Category::factory()->create([
        'slug' => 'slug-lama',
    ]);

    $this->patchJson("/ajax/categories/{$category->id}", [
        'slug' => 'Slug Baru Dengan Spasi',
    ])
        ->assertOk()
        ->assertJsonPath('data.slug', 'slug-baru-dengan-spasi');

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'slug' => 'slug-baru-dengan-spasi',
    ]);
});

test('tag controller stores updates and deletes a tag', function () {
    $post = Post::factory()->create();

    $response = $this->postJson('/ajax/tags', [
        'name' => 'Laravel',
        'slug' => 'laravel',
        'post_ids' => [$post->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Laravel')
        ->assertJsonPath('data.posts.0.id', $post->id);

    $tag = Tag::where('slug', 'laravel')->firstOrFail();

    $this->patchJson("/ajax/tags/{$tag->id}", [
        'name' => 'Laravel Framework',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Laravel Framework');

    $this->deleteJson("/ajax/tags/{$tag->id}")->assertNoContent();
    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
});

test('license controller stores updates and deletes a license', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/ajax/licenses', [
        'user_id' => $user->id,
        'code' => 'APICO-TEST-0001',
        'is_active' => true,
        'expires_at' => now()->addYear()->toDateTimeString(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'APICO-TEST-0001')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.is_active', true);

    $license = License::where('code', 'APICO-TEST-0001')->firstOrFail();

    $this->patchJson("/ajax/licenses/{$license->id}", [
        'is_active' => false,
        'used_at' => now()->toDateTimeString(),
    ])
        ->assertOk()
        ->assertJsonPath('data.code', 'APICO-TEST-0001')
        ->assertJsonPath('data.is_active', false);

    $this->deleteJson("/ajax/licenses/{$license->id}")->assertNoContent();
    $this->assertDatabaseMissing('licenses', ['id' => $license->id]);
});

test('website controller stores updates and deletes a website', function () {
    $response = $this->postJson('/ajax/websites', [
        'domain' => 'example.test',
        'ip_address' => '192.168.1.10',
        'license_key' => 'APICO-WEBSITE-0001',
        'status' => 'active',
        'theme_version' => '1.2.3',
        'plugin_version' => '4.5.6',
        'wp_version' => '6.5.4',
        'php_version' => '8.3',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.domain', 'example.test')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.license_key', 'APICO-WEBSITE-0001');

    $website = Website::where('domain', 'example.test')->firstOrFail();

    $this->patchJson("/ajax/websites/{$website->id}", [
        'status' => 'invalid',
        'plugin_version' => '4.5.7',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'invalid')
        ->assertJsonPath('data.plugin_version', '4.5.7');

    $this->deleteJson("/ajax/websites/{$website->id}")->assertNoContent();
    $this->assertDatabaseMissing('websites', ['id' => $website->id]);
});

test('request log controller stores updates and deletes a request log', function () {
    $website = Website::factory()->create();
    $license = License::factory()->create();

    $response = $this->postJson('/ajax/request-logs', [
        'route' => '/api/validate-license',
        'method' => 'POST',
        'request' => ['license_key' => $license->code],
        'status' => 200,
        'website_id' => $website->id,
        'license_id' => $license->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.route', '/api/validate-license')
        ->assertJsonPath('data.status', 200)
        ->assertJsonPath('data.website.id', $website->id)
        ->assertJsonPath('data.license.id', $license->id);

    $requestLog = RequestLog::where('route', '/api/validate-license')->firstOrFail();

    $this->patchJson("/ajax/request-logs/{$requestLog->id}", [
        'status' => 403,
        'request' => ['error' => 'invalid_license'],
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 403)
        ->assertJsonPath('data.request.error', 'invalid_license');

    $this->deleteJson("/ajax/request-logs/{$requestLog->id}")->assertNoContent();
    $this->assertDatabaseMissing('request_logs', ['id' => $requestLog->id]);
});

test('public project show returns a project by slug', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');

    $parentProject = Project::factory()->create([
        'name' => 'Core Project',
        'slug' => 'core-project',
    ]);

    $project = Project::factory()->for($parentProject, 'parent')->create([
        'name' => 'Velocity Addons',
        'slug' => 'velocity-addons',
        'type' => 'wp_plugin',
        'version' => '2.3.0',
        'package_file' => 'project-packages/velocity-addons/velocity-addons-2-3-0.zip',
        'package_external_url' => 'https://downloads.example.com/velocity-addons.zip',
        'icon' => 'uploads/projects/velocity-addons/icon.png',
        'screenshot' => 'uploads/projects/velocity-addons/screenshot.png',
    ]);

    $response = $this->withHeader('signature', md5(now()->format('dmY')))
        ->getJson("/api/v1/project/{$project->slug}")
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'Success')
        ->assertJsonPath('data.id', $project->id)
        ->assertJsonPath('data.name', 'Velocity Addons')
        ->assertJsonPath('data.slug', 'velocity-addons')
        ->assertJsonPath('data.type', 'wp_plugin')
        ->assertJsonPath('data.package_external_url', 'https://downloads.example.com/velocity-addons.zip')
        ->assertJsonPath('data.download_url', 'https://downloads.example.com/velocity-addons.zip')
        ->assertJsonPath('data.parent.id', $parentProject->id)
        ->assertJsonPath('data.parent.name', 'Core Project');

    expect($response->json('data'))->not->toHaveKey('created_at')
        ->and($response->json('data'))->not->toHaveKey('updated_at');

    Carbon::setTestNow();
});

test('public project show uses package file url as download url when external url is empty', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');

    Storage::fake('public');

    $project = Project::factory()->create([
        'slug' => 'package-only-project',
        'package_file' => 'project-packages/package-only-project/package-only-project.zip',
        'package_external_url' => null,
    ]);

    $this->withHeader('signature', md5(now()->format('dmY')))
        ->getJson("/api/v1/project/{$project->slug}")
        ->assertOk()
        ->assertJsonPath(
            'data.download_url',
            Storage::disk('public')->url('project-packages/package-only-project/package-only-project.zip'),
        );

    Carbon::setTestNow();
});

test('public project show returns not found for unknown slug', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');

    $this->withHeader('signature', md5(now()->format('dmY')))
        ->getJson('/api/v1/project/missing-project')
        ->assertNotFound()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'Project not found');

    Carbon::setTestNow();
});

test('public github webhook can sync project release', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');
    putenv('RELEASE_WEBHOOK_SECRET=test-webhook-secret');
    $_ENV['RELEASE_WEBHOOK_SECRET'] = 'test-webhook-secret';
    $_SERVER['RELEASE_WEBHOOK_SECRET'] = 'test-webhook-secret';

    $project = Project::factory()->create([
        'name' => 'Velocity Addons',
        'slug' => 'velocity-addons',
        'type' => 'wp_plugin',
        'version' => '2.3.0',
        'github_url' => 'https://github.com/example/velocity-addons',
        'package_external_url' => 'https://downloads.example.com/velocity-addons.zip',
    ]);

    $syncedProject = Project::factory()->make([
        'id' => $project->id,
        'name' => $project->name,
        'slug' => $project->slug,
        'type' => $project->type,
        'version' => '2.4.0',
        'github_url' => $project->github_url,
        'package_file' => 'project-packages/velocity-addons/velocity-addons-2-4-0.zip',
        'package_external_url' => null,
        'parent_id' => $project->parent_id,
        'plugin_wp_required' => $project->plugin_wp_required,
        'requires_php' => $project->requires_php,
        'requires_wp' => $project->requires_wp,
        'description' => $project->description,
        'created_at' => $project->created_at,
        'updated_at' => now(),
    ]);

    $service = Mockery::mock(GithubService::class);
    $service->shouldReceive('syncGithubProjectRelease')
        ->once()
        ->with($project->id)
        ->andReturn($syncedProject);

    app()->instance(GithubService::class, $service);

    $signature = md5('test-webhook-secret');

    $this->withHeader('X-Signature', $signature)
        ->post("/api/v1/release-project/{$project->slug}")
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'Success')
        ->assertJsonPath('data.id', $project->id)
        ->assertJsonPath('data.version', '2.4.0')
        ->assertJsonPath('data.package_external_url', null)
        ->assertJsonPath('data.icon', 'uploads/projects/velocity-addons/icon.png')
        ->assertJsonPath('data.screenshot', 'uploads/projects/velocity-addons/screenshot.png')
        ->assertJsonPath('data.package_file', 'project-packages/velocity-addons/velocity-addons-2-4-0.zip');

    Carbon::setTestNow();
});

test('public github webhook returns validation error when project has no github url', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');
    putenv('RELEASE_WEBHOOK_SECRET=test-webhook-secret');
    $_ENV['RELEASE_WEBHOOK_SECRET'] = 'test-webhook-secret';
    $_SERVER['RELEASE_WEBHOOK_SECRET'] = 'test-webhook-secret';

    $project = Project::factory()->create([
        'github_url' => null,
    ]);

    $signature = md5('test-webhook-secret');

    $this->withHeader('X-Signature', $signature)
        ->post("/api/v1/release-project/{$project->slug}")
        ->assertStatus(422)
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'Project must have a GitHub URL before syncing releases.');

    Carbon::setTestNow();
});

test('public project update returns not found for unknown slug', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');
    putenv('RELEASE_WEBHOOK_SECRET=test-webhook-secret');
    $_ENV['RELEASE_WEBHOOK_SECRET'] = 'test-webhook-secret';
    $_SERVER['RELEASE_WEBHOOK_SECRET'] = 'test-webhook-secret';

    $signature = md5('test-webhook-secret');

    $this->withHeader('X-Signature', $signature)
        ->post('/api/v1/release-project/missing-project', [
            'name' => 'Missing Project',
        ])
        ->assertNotFound()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'Project not found');

    Carbon::setTestNow();
});

test('public tgm plugins returns wordpress plugins in tgm format', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');

    Storage::fake('public');

    $requiredPlugin = Project::factory()->create([
        'name' => 'Beaver Builder',
        'slug' => 'bb-plugin',
        'type' => 'wp_plugin',
        'plugin_wp_required' => true,
        'version' => '2.10.0.7',
        'package_external_url' => 'https://api.velocitydeveloper.id/plugins/bb-plugin-standard.zip',
        'package_file' => 'project-packages/bb-plugin/bb-plugin-standard.zip',
    ]);

    $optionalPlugin = Project::factory()->create([
        'name' => 'Velocity Blocks',
        'slug' => 'velocity-blocks',
        'type' => 'wp_plugin',
        'plugin_wp_required' => false,
        'version' => null,
        'package_external_url' => null,
        'package_file' => 'project-packages/velocity-blocks/velocity-blocks.zip',
    ]);

    Project::factory()->create([
        'name' => 'Velocity Theme',
        'slug' => 'velocity-theme',
        'type' => 'wp_theme',
    ]);

    $response = $this->withHeader('signature', md5(now()->format('dmY')))
        ->getJson('/api/v1/tgm-plugins')
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'Success')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', $requiredPlugin->name)
        ->assertJsonPath('data.0.slug', $requiredPlugin->slug)
        ->assertJsonPath('data.0.source', 'https://api.velocitydeveloper.id/plugins/bb-plugin-standard.zip')
        ->assertJsonPath('data.0.required', true)
        ->assertJsonPath('data.0.version', '2.10.0.7')
        ->assertJsonPath('data.0.force_activation', false)
        ->assertJsonPath('data.0.force_deactivation', false)
        ->assertJsonPath('data.0.external_url', '')
        ->assertJsonPath('data.0.is_callable', '')
        ->assertJsonPath(
            'data.1.source',
            Storage::disk('public')->url('project-packages/velocity-blocks/velocity-blocks.zip'),
        )
        ->assertJsonPath('data.1.name', $optionalPlugin->name)
        ->assertJsonPath('data.1.required', false)
        ->assertJsonPath('data.1.version', '');

    expect($response->json('data.0'))->not->toHaveKey('type')
        ->and($response->json('data.1'))->not->toHaveKey('type');

    Carbon::setTestNow();
});
