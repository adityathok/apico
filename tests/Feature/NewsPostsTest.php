<?php

use App\Models\Category;
use App\Models\License;
use App\Models\Post;
use App\Models\Tag;

test('news posts can be filtered by category and paginated', function () {
    config()->set('app.url', 'https://api.example.com');

    $license = License::factory()->create([
        'expires_at' => now()->addDay(),
    ]);
    $selectedCategory = Category::factory()->create();
    $otherCategory = Category::factory()->create();
    $selectedPosts = Post::factory()->count(2)->create([
        'created_at' => now()->subMinute(),
    ]);
    $latestSelectedPost = Post::factory()->create([
        'created_at' => now(),
        'image' => 'post/26-06/latest.jpg',
    ]);
    $latestSelectedPost->tags()->attach([
        Tag::factory()->create(['name' => 'Gunung']),
        Tag::factory()->create(['name' => 'Kebakaran']),
        Tag::factory()->create(['name' => 'Pasar Raya']),
    ]);
    $selectedPosts->push($latestSelectedPost);
    $otherPost = Post::factory()->create();

    $selectedCategory->posts()->attach($selectedPosts);
    $otherCategory->posts()->attach($otherPost);

    $response = $this->withHeader('License', $license->code)
        ->getJson("/api/v1/news/posts?category_id={$selectedCategory->id}&post_per_page=2");

    $response->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'Success')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('pagination.current_page', 1)
        ->assertJsonPath('pagination.last_page', 2)
        ->assertJsonPath('pagination.per_page', 2)
        ->assertJsonPath('pagination.total', 3)
        ->assertJsonPath('data.0.image_url', 'https://api.example.com/storage/post/26-06/latest.jpg')
        ->assertJsonPath('data.0.post_tag', 'Gunung, Kebakaran, Pasar Raya')
        ->assertJsonMissing(['id' => $otherPost->id]);
});

test('news posts validates its filters', function () {
    $license = License::factory()->create([
        'expires_at' => now()->addDay(),
    ]);

    $this->withHeader('License', $license->code)
        ->getJson('/api/v1/news/posts?category_id=999999&post_per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['category_id', 'post_per_page']);
});
