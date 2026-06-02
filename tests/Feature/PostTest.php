<?php

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('posts table has the expected columns', function () {
    expect(Schema::hasColumns('posts', [
        'id',
        'user_id',
        'title',
        'slug',
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
