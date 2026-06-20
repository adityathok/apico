<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/api/ai/public/ping', fn () => response()->json([
        'status' => true,
        'message' => 'OK',
    ]))->middleware('public.ai.signature');
});

test('ai public route requires a signature header', function () {
    $this->getJson('/api/ai/public/ping')
        ->assertUnauthorized()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'Signature header is required.');
});

test('ai public route rejects an invalid signature header', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');

    $this->withHeader('signature', 'invalid-signature')
        ->getJson('/api/ai/public/ping')
        ->assertForbidden()
        ->assertJsonPath('status', false)
        ->assertJsonPath('message', 'Signature header is invalid.');

    Carbon::setTestNow();
});

test('ai public route accepts a valid signature header', function () {
    Carbon::setTestNow('2026-06-20 09:00:00');

    $signature = md5(now()->format('dmY'));

    $this->withHeader('signature', $signature)
        ->getJson('/api/ai/public/ping')
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('message', 'OK');

    Carbon::setTestNow();
});
