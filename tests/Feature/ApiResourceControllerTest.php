<?php

use App\Models\Category;
use App\Models\License;
use App\Models\Post;
use App\Models\RequestLog;
use App\Models\Tag;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $license = License::factory()->create([
        'code' => 'APICO-API-TEST',
    ]);

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
        ->and($post->image)->toStartWith('post/' . now()->format('y-m') . '/');

    Storage::disk('public')->assertExists($post->image);
});

test('post controller updates and deletes a post', function () {
    Storage::fake('public');

    $oldImage = 'post/' . now()->format('y-m') . '/old.jpg';
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
