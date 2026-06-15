<?php

use App\Models\License;
use App\Models\RequestLog;
use App\Models\Website;
use Illuminate\Support\Facades\Route;

test('api requests require a license header', function () {
    $this->getJson('/api/v1/news/categories')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'License key is required.');

    $requestLog = RequestLog::sole();

    expect($requestLog->route)->toBe('/api/v1/news/categories')
        ->and($requestLog->method)->toBe('GET')
        ->and($requestLog->status)->toBe(401)
        ->and($requestLog->website_id)->toBeNull()
        ->and($requestLog->license_id)->toBeNull();
});

test('api requests reject invalid license keys', function () {
    License::factory()->create([
        'code' => 'APICO-VALID-0001',
    ]);

    $this->withHeader('License', 'APICO-INVALID')
        ->getJson('/api/v1/news/categories')
        ->assertForbidden()
        ->assertJsonPath('message', 'License key is invalid.');

    $requestLog = RequestLog::sole();

    expect($requestLog->status)->toBe(403)
        ->and($requestLog->license_id)->toBeNull();
});

test('api requests reject inactive license keys', function () {
    $license = License::factory()->create([
        'is_active' => false,
    ]);

    $this->withHeader('License', $license->code)
        ->getJson('/api/v1/news/categories')
        ->assertForbidden()
        ->assertJsonPath('message', 'License key is invalid.');

    expect(RequestLog::sole()->license_id)->toBe($license->id);
});

test('api requests reject expired license keys', function () {
    $license = License::factory()->create([
        'expires_at' => now()->subMinute(),
    ]);

    $this->withHeader('License', $license->code)
        ->getJson('/api/v1/news/categories')
        ->assertForbidden()
        ->assertJsonPath('message', 'License key is invalid.');

    expect(RequestLog::sole()->license_id)->toBe($license->id);
});

test('api requests accept active license keys', function () {
    $license = License::factory()->create([
        'expires_at' => now()->addDay(),
    ]);
    $website = Website::factory()->create([
        'license_key' => $license->code,
    ]);

    $this->withHeader('License', $license->code)
        ->getJson('/api/v1/news/categories?source=wordpress')
        ->assertOk();

    $requestLog = RequestLog::sole();

    expect($requestLog->route)->toBe('/api/v1/news/categories')
        ->and($requestLog->method)->toBe('GET')
        ->and($requestLog->request)->toBe(['source' => 'wordpress'])
        ->and($requestLog->status)->toBe(200)
        ->and($requestLog->website_id)->toBe($website->id)
        ->and($requestLog->license_id)->toBe($license->id);
});

test('api requests are logged when the endpoint throws an exception', function () {
    $license = License::factory()->create([
        'expires_at' => now()->addDay(),
    ]);

    Route::get('/api/v1/failing-request', fn () => throw new RuntimeException('Endpoint failed.'))
        ->middleware('license');

    $this->withHeader('License', $license->code)
        ->getJson('/api/v1/failing-request')
        ->assertInternalServerError();

    $requestLog = RequestLog::sole();

    expect($requestLog->route)->toBe('/api/v1/failing-request')
        ->and($requestLog->status)->toBe(500)
        ->and($requestLog->license_id)->toBe($license->id);
});
