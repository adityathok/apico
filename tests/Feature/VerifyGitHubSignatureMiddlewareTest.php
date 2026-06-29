<?php

use App\Models\RequestLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    putenv('RELEASE_WEBHOOK_SECRET=test-webhook-secret');
    $_ENV['RELEASE_WEBHOOK_SECRET'] = 'test-webhook-secret';
    $_SERVER['RELEASE_WEBHOOK_SECRET'] = 'test-webhook-secret';

    Route::post('/api/github/webhook/ping', fn () => response()->json([
        'status' => true,
        'message' => 'OK',
    ]))->middleware('github.signature');
});

test('github webhook route requires an x-signature header', function () {
    $this->postJson('/api/github/webhook/ping')
        ->assertUnauthorized()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'X-Signature header is required.');

    $requestLog = RequestLog::sole();

    expect($requestLog->route)->toBe('/api/github/webhook/ping')
        ->and($requestLog->method)->toBe('POST')
        ->and($requestLog->status)->toBe(401)
        ->and($requestLog->website_id)->toBeNull()
        ->and($requestLog->license_id)->toBeNull();
});

test('github webhook route rejects an invalid x-signature header', function () {
    Carbon::setTestNow('2026-06-22 12:00:00');

    $this->withHeader('X-Signature', 'invalid-signature')
        ->postJson('/api/github/webhook/ping')
        ->assertForbidden()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'X-Signature header is invalid.');

    $requestLog = RequestLog::sole();

    expect($requestLog->route)->toBe('/api/github/webhook/ping')
        ->and($requestLog->method)->toBe('POST')
        ->and($requestLog->status)->toBe(403)
        ->and($requestLog->website_id)->toBeNull()
        ->and($requestLog->license_id)->toBeNull();

    Carbon::setTestNow();
});

test('github webhook route accepts a valid x-signature header', function () {
    $signature = md5('test-webhook-secret');

    $this->withHeader('X-Signature', $signature)
        ->postJson('/api/github/webhook/ping')
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'OK');

    $requestLog = RequestLog::sole();

    expect($requestLog->route)->toBe('/api/github/webhook/ping')
        ->and($requestLog->method)->toBe('POST')
        ->and($requestLog->status)->toBe(200)
        ->and($requestLog->website_id)->toBeNull()
        ->and($requestLog->license_id)->toBeNull();

    Carbon::setTestNow();
});

test('github webhook route logs downstream http exceptions', function () {
    Carbon::setTestNow('2026-06-22 12:00:00');

    Route::post('/api/github/webhook/fail', fn () => abort(409))->middleware('github.signature');

    $signature = md5('test-webhook-secret');

    $this->withHeader('X-Signature', $signature)
        ->postJson('/api/github/webhook/fail')
        ->assertStatus(409);

    $requestLog = RequestLog::sole();

    expect($requestLog->route)->toBe('/api/github/webhook/fail')
        ->and($requestLog->method)->toBe('POST')
        ->and($requestLog->status)->toBe(409)
        ->and($requestLog->website_id)->toBeNull()
        ->and($requestLog->license_id)->toBeNull();

    Carbon::setTestNow();
});
