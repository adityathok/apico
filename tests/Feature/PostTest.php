<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\PostSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

test('posts table has the expected columns', function () {
    expect(Schema::hasColumns('posts', [
        'id',
        'user_id',
        'title',
        'slug',
        'image',
        'image_caption',
        'excerpt',
        'content',
        'published_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('a post belongs to a user', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create();

    expect($post->user)
        ->toBeInstanceOf(User::class)
        ->id->toBe($user->id);
});

test('published at is cast to datetime', function () {
    $post = Post::factory()->create([
        'published_at' => now(),
    ]);

    expect($post->published_at)->toBeInstanceOf(DateTimeInterface::class);
});

test('image fields are mass assignable', function () {
    $post = Post::factory()->create([
        'image' => 'posts/example.jpg',
        'image_caption' => 'Example image caption.',
    ]);

    expect($post->image)->toBe('posts/example.jpg')
        ->and($post->image_caption)->toBe('Example image caption.');
});

test('read route renders a post blade page by slug', function () {
    $post = Post::factory()->create([
        'title' => 'Public Read Page',
        'slug' => 'public-read-page',
        'image' => null,
        'image_caption' => 'Readable image caption.',
        'excerpt' => 'Readable post excerpt.',
        'content' => '<p>Readable post content.</p>',
        'published_at' => now(),
    ]);

    $this->get(route('read', $post->slug))
        ->assertOk()
        ->assertViewIs('posts.read')
        ->assertViewHas('post', $post)
        ->assertSee('Public Read Page')
        ->assertSee('Readable post excerpt.')
        ->assertSee('Readable post content.', false);
});

test('read route returns not found for an unknown slug', function () {
    $this->get(route('read', 'missing-post'))->assertNotFound();
});

test('public posts route renders post cards with the frontend layout', function () {
    $post = Post::factory()->create([
        'title' => 'Card Post Title',
        'slug' => 'card-post-title',
        'image' => null,
        'excerpt' => 'Card post excerpt.',
        'published_at' => now(),
    ]);

    $this->get('/posts')
        ->assertOk()
        ->assertViewIs('posts.index')
        ->assertViewHas('posts')
        ->assertSee('Latest Posts')
        ->assertSee('Card Post Title')
        ->assertSee('Card post excerpt.')
        ->assertSee(route('read', $post->slug), false);
});

test('store normalizes slug with str slug before saving', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/posts', [
            'user_id' => $user->id,
            'title' => 'Store Post',
            'slug' => 'Slug Campur Spasi & Simbol',
            'content' => 'Isi artikel untuk pengujian.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'slug-campur-spasi-simbol');

    $this->assertDatabaseHas('posts', [
        'title' => 'Store Post',
        'slug' => 'slug-campur-spasi-simbol',
    ]);
});

test('update normalizes slug with str slug before saving', function () {
    $user = User::factory()->create();
    $post = Post::factory()->for($user)->create([
        'slug' => 'slug-lama',
    ]);

    $this->actingAs($user)
        ->putJson('/api/posts/'.$post->id, [
            'slug' => 'Slug Baru Dengan Spasi',
        ])
        ->assertOk()
        ->assertJsonPath('data.slug', 'slug-baru-dengan-spasi');

    $this->assertDatabaseHas('posts', [
        'id' => $post->id,
        'slug' => 'slug-baru-dengan-spasi',
    ]);
});

test('index can filter posts by category id', function () {
    $user = User::factory()->create();
    $selectedCategory = Category::factory()->create();
    $otherCategory = Category::factory()->create();

    $matchingPost = Post::factory()->for($user)->create([
        'title' => 'Matching Post',
        'slug' => 'matching-post',
    ]);
    $matchingPost->categories()->sync([$selectedCategory->id]);

    $otherPost = Post::factory()->for($user)->create([
        'title' => 'Other Post',
        'slug' => 'other-post',
    ]);
    $otherPost->categories()->sync([$otherCategory->id]);

    $this->actingAs($user)
        ->getJson('/api/posts?category_id='.$selectedCategory->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchingPost->id);
});

test('index can use custom posts per page', function () {
    $user = User::factory()->create();

    Post::factory()->count(3)->for($user)->create();

    $this->actingAs($user)
        ->getJson('/api/posts?post_per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2);
});

test('post seeder creates posts for seeded users once', function () {
    Http::fake([
        'picsum.photos/*' => Http::response('fake picsum image', 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);
    Storage::fake('public');

    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $testUser = User::factory()->create(['email' => 'usertest@example.com']);

    $this->seed(PostSeeder::class);
    $this->seed(PostSeeder::class);

    expect($admin->posts()->count())->toBe(5)
        ->and($testUser->posts()->count())->toBe(5)
        ->and(Post::count())->toBe(10)
        ->and(Post::query()->pluck('image')->every(
            fn (?string $image): bool => is_string($image) && str_starts_with($image, 'post/'.now()->format('y-m').'/'),
        ))->toBeTrue();

    Post::query()
        ->pluck('image')
        ->each(fn (string $image): mixed => Storage::disk('public')->assertExists($image));

    Http::assertSentCount(10);
});
