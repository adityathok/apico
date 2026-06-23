<?php

use App\Models\User;
use App\Services\AiProviderService;

test('authenticated users can generate article content from a topic', function () {
    $user = User::factory()->create();
    $expectedArticle = [
        'title' => 'Panduan Laravel Testing',
        'content' => '<p>Isi artikel pengujian.</p>',
        'excerpt' => 'Ringkasan artikel pengujian.',
        'tags' => ['laravel', 'testing'],
        'image_keyword' => 'testing',
    ];

    $service = Mockery::mock(AiProviderService::class);
    $service->shouldReceive('article_generator')
        ->once()
        ->with('Laravel testing')
        ->andReturn($expectedArticle);

    app()->instance(AiProviderService::class, $service);

    $this->actingAs($user)
        ->withoutMiddleware()
        ->postJson('/ajax/article-generator', [
            'topic' => 'Laravel testing',
        ])
        ->assertOk()
        ->assertJson([
            'data' => $expectedArticle,
        ]);
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
