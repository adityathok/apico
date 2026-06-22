<?php

use App\Models\Category;
use App\Models\License;
use App\Models\Post;
use App\Models\Project;
use App\Models\RequestLog;
use App\Models\Tag;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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

test('public project can be updated by slug with a package upload', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');
    putenv('RELEASE_WEBHOOK_SECRET=test-webhook-secret');
    $_ENV['RELEASE_WEBHOOK_SECRET'] = 'test-webhook-secret';
    $_SERVER['RELEASE_WEBHOOK_SECRET'] = 'test-webhook-secret';

    Storage::fake('public');

    $parentProject = Project::factory()->create([
        'name' => 'Parent Project',
        'slug' => 'parent-project',
    ]);

    $project = Project::factory()->create([
        'name' => 'Velocity Addons',
        'slug' => 'velocity-addons',
        'type' => 'wp_plugin',
        'version' => '2.3.0',
        'requires_wp' => '6.7',
        'requires_php' => '8.2',
        'plugin_wp_required' => false,
        'package_file' => 'project-packages/velocity-addons/velocity-addons-2-3-0.zip',
        'package_external_url' => 'https://downloads.example.com/velocity-addons.zip',
        'parent_id' => null,
    ]);
    Storage::disk('public')->put($project->package_file, 'old package');

    $package = UploadedFile::fake()->create('velocity-addons-pro.zip', 240, 'application/zip');

    $signature = hash_hmac(
        'sha256',
        Carbon::now('Asia/Jakarta')->format('dmY'),
        'test-webhook-secret',
    );

    $this->withHeader('X-Signature', $signature)
        ->post("/api/v1/release-project/{$project->slug}", [
            'name' => 'Velocity Addons Pro',
            'slug' => 'Velocity Addons Pro',
            'type' => 'wp_plugin',
            'version' => '2.4.0',
            'requires_wp' => '6.9.4',
            'requires_php' => '8.1.34',
            'plugin_wp_required' => true,
            'github_url' => 'https://github.com/example/velocity-addons-pro',
            'package_external_url' => 'https://downloads.example.com/velocity-addons-pro.zip',
            'description' => 'Updated plugin package.',
            'parent_id' => $parentProject->id,
            'package_file' => $package,
        ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'Success')
        ->assertJsonPath('data.name', 'Velocity Addons Pro')
        ->assertJsonPath('data.slug', 'velocity-addons-pro')
        ->assertJsonPath('data.type', 'wp_plugin')
        ->assertJsonPath('data.requires', '6.9.4')
        ->assertJsonPath('data.requires_php', '8.1.34')
        ->assertJsonPath('data.plugin_wp_required', true)
        ->assertJsonPath('data.parent.id', $parentProject->id)
        ->assertJsonPath('data.parent.name', 'Parent Project')
        ->assertJsonPath('data.download_url', 'https://downloads.example.com/velocity-addons-pro.zip');

    $project->refresh();

    expect($project->name)->toBe('Velocity Addons Pro')
        ->and($project->slug)->toBe('velocity-addons-pro')
        ->and($project->version)->toBe('2.4.0')
        ->and($project->requires_wp)->toBe('6.9.4')
        ->and($project->requires_php)->toBe('8.1.34')
        ->and($project->plugin_wp_required)->toBeTrue()
        ->and($project->parent_id)->toBe($parentProject->id)
        ->and($project->package_external_url)->toBe('https://downloads.example.com/velocity-addons-pro.zip')
        ->and($project->package_file)->toStartWith('project-packages/velocity-addons-pro/');

    Storage::disk('public')->assertMissing('project-packages/velocity-addons/velocity-addons-2-3-0.zip');
    Storage::disk('public')->assertExists($project->package_file);

    Carbon::setTestNow();
});

test('public project update returns not found for unknown slug', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');
    putenv('RELEASE_WEBHOOK_SECRET=test-webhook-secret');
    $_ENV['RELEASE_WEBHOOK_SECRET'] = 'test-webhook-secret';
    $_SERVER['RELEASE_WEBHOOK_SECRET'] = 'test-webhook-secret';

    $signature = hash_hmac(
        'sha256',
        Carbon::now('Asia/Jakarta')->format('dmY'),
        'test-webhook-secret',
    );

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
