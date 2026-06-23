<?php

use App\Services\UnsplashService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('unsplash service searches photos using the configured api credentials', function () {
    config()->set('services.unsplash.url', 'https://api.unsplash.com');
    config()->set('services.unsplash.access_key', 'test-unsplash-key');

    Http::fake([
        'api.unsplash.com/search/photos*' => Http::response([
            'total' => 1,
            'total_pages' => 1,
            'results' => [
                [
                    'id' => 'photo-1',
                    'description' => 'A sample photo',
                ],
            ],
        ]),
    ]);

    $result = app(UnsplashService::class)->searchPhotos('nature', 2, 15, 'portrait');

    expect($result)
        ->toBeArray()
        ->and($result['total'])->toBe(1)
        ->and($result['results'][0]['id'])->toBe('photo-1');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.unsplash.com/search/photos?query=nature&page=2&per_page=15&orientation=portrait'
            && $request->hasHeader('Authorization', 'Client-ID test-unsplash-key')
            && $request->hasHeader('Accept', 'application/json');
    });
});
