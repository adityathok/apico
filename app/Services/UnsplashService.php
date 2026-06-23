<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class UnsplashService
{
    public function searchPhotos(
        string $query,
        int $page = 1,
        int $perPage = 10,
        string $orientation = 'landscape',
    ): array {
        return Http::baseUrl((string) config('services.unsplash.url'))
            ->acceptJson()
            ->withHeaders([
                'Authorization' => 'Client-ID '.config('services.unsplash.access_key'),
            ])
            ->get('/search/photos', [
                'query' => $query,
                'page' => $page,
                'per_page' => $perPage,
                'orientation' => $orientation,
            ])
            ->throw()
            ->json();
    }
}
