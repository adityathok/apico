<?php

use App\Models\Category;
use App\Models\License;
use App\Models\Post;

test('news categories returns all categories', function () {
    $license = License::factory()->create([
        'expires_at' => now()->addDay(),
    ]);
    $business = Category::factory()->create([
        'name' => 'Bisnis',
        'slug' => 'bisnis',
        'description' => '',
    ]);
    $architecture = Category::factory()->create([
        'name' => 'Arsitektur',
        'slug' => 'arsitektur',
        'description' => '',
    ]);

    $architecture->posts()->attach(Post::factory()->count(2)->create());
    $business->posts()->attach(Post::factory()->create());

    $response = $this->withHeader('License', $license->code)
        ->getJson('/api/v1/news/categories');

    $response->assertOk()
        ->assertExactJson([
            'status' => true,
            'message' => 'Success',
            'data' => [
                [
                    'id' => $architecture->id,
                    'name' => 'Arsitektur',
                    'slug' => 'arsitektur',
                    'description' => '',
                    'count' => 2,
                ],
                [
                    'id' => $business->id,
                    'name' => 'Bisnis',
                    'slug' => 'bisnis',
                    'description' => '',
                    'count' => 1,
                ],
            ],
        ]);
});
