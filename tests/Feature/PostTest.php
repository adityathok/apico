<?php

use App\Models\Post;
use App\Models\User;
use Database\Seeders\PostSeeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

test('posts table has the expected columns', function () {
    expect(Schema::hasColumns('posts', [
        'id',
        'user_id',
        'title',
        'slug',
        'image',
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

test('image path is mass assignable', function () {
    $post = Post::factory()->create([
        'image' => 'posts/example.jpg',
    ]);

    expect($post->image)->toBe('posts/example.jpg');
});

test('post seeder creates posts for seeded users once', function () {
    Storage::fake('public');

    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $testUser = User::factory()->create(['email' => 'test@example.com']);

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
});
