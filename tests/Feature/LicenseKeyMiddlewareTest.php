<?php

use App\Models\License;

test('api requests require a license header', function () {
    $this->getJson('/api/posts')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'License key is required.');
});

test('api requests reject invalid license keys', function () {
    License::factory()->create([
        'code' => 'APICO-VALID-0001',
    ]);

    $this->withHeader('License', 'APICO-INVALID')
        ->getJson('/api/posts')
        ->assertForbidden()
        ->assertJsonPath('message', 'License key is invalid.');
});

test('api requests reject inactive license keys', function () {
    $license = License::factory()->create([
        'is_active' => false,
    ]);

    $this->withHeader('License', $license->code)
        ->getJson('/api/posts')
        ->assertForbidden()
        ->assertJsonPath('message', 'License key is invalid.');
});

test('api requests reject expired license keys', function () {
    $license = License::factory()->create([
        'expires_at' => now()->subMinute(),
    ]);

    $this->withHeader('License', $license->code)
        ->getJson('/api/posts')
        ->assertForbidden()
        ->assertJsonPath('message', 'License key is invalid.');
});

test('api requests accept active license keys', function () {
    $license = License::factory()->create([
        'expires_at' => now()->addDay(),
    ]);

    $this->withHeader('License', $license->code)
        ->getJson('/api/posts')
        ->assertOk();
});
