<?php

use App\Ai\Agents\ArticleGenerator;
use App\Models\User;

test('authenticated users can generate article content from a topic', function () {
    $user = User::factory()->create();
    $expectedArticle = [
        'title' => 'Panduan Laravel Testing',
        'content' => '<p>Isi artikel pengujian.</p>',
        'excerpt' => 'Ringkasan artikel pengujian.',
        'tags' => ['laravel', 'testing'],
    ];

    ArticleGenerator::fake([$expectedArticle]);

    $this->actingAs($user)
        ->withoutMiddleware()
        ->postJson('/ajax/article-generator', [
            'topic' => 'Laravel testing',
        ])
        ->assertOk()
        ->assertJson([
            'data' => $expectedArticle,
        ]);

    ArticleGenerator::assertPrompted(
        'Buatkan artikel menarik tentang: Laravel testing',
    );
});

test('article generator validates the topic field', function () {
    $user = User::factory()->create();
    $session = app('session');
    $session->start();
    $token = $session->token();

    $this->actingAs($user)
        ->withSession([
            '_token' => $token,
        ])
        ->withHeader('X-CSRF-TOKEN', $token)
        ->post('/ajax/article-generator', [
            '_token' => $token,
            'topic' => '',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['topic']);
});
