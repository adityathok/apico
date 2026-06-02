<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;

test('post controller stores a post with category and tag pivots', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();

    $response = $this->postJson('/api/posts', [
        'user_id' => $user->id,
        'title' => 'Getting Started With APIs',
        'slug' => 'getting-started-with-apis',
        'image' => 'posts/api.jpg',
        'excerpt' => 'A short API intro.',
        'content' => 'Complete API article content.',
        'published_at' => now()->toDateTimeString(),
        'category_ids' => [$category->id],
        'tag_ids' => [$tag->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Getting Started With APIs')
        ->assertJsonPath('data.categories.0.id', $category->id)
        ->assertJsonPath('data.tags.0.id', $tag->id);

    $post = Post::where('slug', 'getting-started-with-apis')->firstOrFail();

    expect($post->categories()->pluck('categories.id')->all())->toBe([$category->id])
        ->and($post->tags()->pluck('tags.id')->all())->toBe([$tag->id]);
});

test('post controller updates and deletes a post', function () {
    $post = Post::factory()->create();
    $category = Category::factory()->create();

    $this->patchJson("/api/posts/{$post->id}", [
        'title' => 'Updated Post Title',
        'category_ids' => [$category->id],
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated Post Title')
        ->assertJsonPath('data.categories.0.id', $category->id);

    $this->deleteJson("/api/posts/{$post->id}")->assertNoContent();

    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
});

test('category controller stores updates and deletes a category', function () {
    $post = Post::factory()->create();

    $response = $this->postJson('/api/categories', [
        'name' => 'News',
        'slug' => 'news',
        'description' => 'Fresh updates.',
        'post_ids' => [$post->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'News')
        ->assertJsonPath('data.posts.0.id', $post->id);

    $category = Category::where('slug', 'news')->firstOrFail();

    $this->patchJson("/api/categories/{$category->id}", [
        'name' => 'Product News',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Product News');

    $this->deleteJson("/api/categories/{$category->id}")->assertNoContent();
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('tag controller stores updates and deletes a tag', function () {
    $post = Post::factory()->create();

    $response = $this->postJson('/api/tags', [
        'name' => 'Laravel',
        'slug' => 'laravel',
        'post_ids' => [$post->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Laravel')
        ->assertJsonPath('data.posts.0.id', $post->id);

    $tag = Tag::where('slug', 'laravel')->firstOrFail();

    $this->patchJson("/api/tags/{$tag->id}", [
        'name' => 'Laravel Framework',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Laravel Framework');

    $this->deleteJson("/api/tags/{$tag->id}")->assertNoContent();
    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
});
