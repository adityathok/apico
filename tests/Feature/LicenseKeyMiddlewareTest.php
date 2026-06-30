<?php

use App\Models\License;
use App\Models\RequestLog;
use App\Models\Server;
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
        'domain' => 'existing.example.com',
        'license_key' => $license->code,
    ]);

    $this->withHeaders([
        'License' => $license->code,
        'source' => $website->domain,
    ])
        ->getJson('/api/v1/news/categories?type=wordpress')
        ->assertOk();

    $requestLog = RequestLog::sole();

    expect($requestLog->route)->toBe('/api/v1/news/categories')
        ->and($requestLog->method)->toBe('GET')
        ->and($requestLog->request)->toBe(['type' => 'wordpress'])
        ->and($requestLog->status)->toBe(200)
        ->and($requestLog->website_id)->toBe($website->id)
        ->and($requestLog->license_id)->toBe($license->id);
});

test('api requests create websites from the source header', function () {
    $license = License::factory()->create([
        'expires_at' => now()->addDay(),
    ]);

    foreach (['first.example.com', 'second.example.com'] as $source) {
        $this->withHeaders([
            'License' => $license->code,
            'source' => $source,
        ])->getJson('/api/v1/news/categories')->assertOk();
    }

    $firstWebsite = Website::where('domain', 'first.example.com')->sole();
    $secondWebsite = Website::where('domain', 'second.example.com')->sole();

    expect(Website::count())->toBe(2)
        ->and($firstWebsite->license_key)->toBe($license->code)
        ->and($secondWebsite->license_key)->toBe($license->code)
        ->and(RequestLog::where('website_id', $firstWebsite->id)->count())->toBe(1)
        ->and(RequestLog::where('website_id', $secondWebsite->id)->count())->toBe(1);
});

test('api requests store website version metadata from the payload', function () {
    $license = License::factory()->create([
        'expires_at' => now()->addDay(),
    ]);

    $this->withHeaders([
        'License' => $license->code,
        'source' => 'payload.example.com',
    ])->getJson('/api/v1/license?wp_version=6.9.4&php_version=8.1.34&velocity_addons_version=2.0.10')
        ->assertOk();

    $website = Website::where('domain', 'payload.example.com')->sole();

    expect($website->license_key)->toBe($license->code)
        ->and($website->status)->toBe('active')
        ->and($website->wp_version)->toBe('6.9.4')
        ->and($website->php_version)->toBe('8.1.34')
        ->and($website->plugin_version)->toBe('2.0.10');
});

test('api requests update existing website version metadata from the payload', function () {
    $license = License::factory()->create([
        'expires_at' => now()->addDay(),
    ]);
    $website = Website::factory()->create([
        'domain' => 'existing-payload.example.com',
        'license_key' => 'OLD-LICENSE',
        'status' => 'invalid',
        'wp_version' => '6.8.1',
        'php_version' => '8.0.30',
        'plugin_version' => '1.0.0',
    ]);

    $this->withHeaders([
        'License' => $license->code,
        'source' => $website->domain,
    ])->getJson('/api/v1/license?wp_version=6.9.4&php_version=8.1.34&velocity_addons_version=2.0.10')
        ->assertOk();

    $website->refresh();

    expect($website->license_key)->toBe($license->code)
        ->and($website->status)->toBe('active')
        ->and($website->wp_version)->toBe('6.9.4')
        ->and($website->php_version)->toBe('8.1.34')
        ->and($website->plugin_version)->toBe('2.0.10');
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

test('get-license route rejects unregistered server ip addresses', function () {
    $license = License::factory()->create([
        'code' => 'APICO-LICENSE-0001',
        'expires_at' => now()->addDay(),
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.10.99'])
        ->withHeader('License', $license->code)
        ->withHeader('source', 'blocked.example.com')
        ->getJson('/api/v1/get-license')
        ->assertForbidden()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'IP address is not registered.');

    expect(RequestLog::where('status', 403)->count())->toBe(1)
        ->and(RequestLog::where('license_id', $license->id)->exists())->toBeTrue();
});
test('get-license route accepts registered server ip addresses', function () {
    $license = License::factory()->create([
        'code' => 'APICO-LICENSE-0002',
        'expires_at' => now()->addDay(),
    ]);
    Server::factory()->create([
        'server_ip' => '192.168.10.20',
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.10.20'])
        ->withHeader('License', $license->code)
        ->getJson('/api/v1/get-license')
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.code', $license->code)
        ->assertJsonPath('data.is_active', true);

    expect(RequestLog::where('status', 200)->count())->toBe(2)
        ->and(RequestLog::where('license_id', $license->id)->exists())->toBeTrue();
});

test('get-auto-license route returns latest license code for registered server ip addresses', function () {
    License::factory()->create([
        'code' => 'APICO-LICENSE-0003',
        'created_at' => now()->subMinute(),
        'expires_at' => now()->addDay(),
    ]);
    $latestLicense = License::factory()->create([
        'code' => 'APICO-LICENSE-0004',
        'created_at' => now(),
        'expires_at' => now()->addDay(),
    ]);
    Server::factory()->create([
        'server_ip' => '192.168.10.20',
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.10.20'])
        ->getJson('/api/v1/get-auto-license')
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.code', $latestLicense->code)
        ->assertJsonPath('data.is_active', true);
});
