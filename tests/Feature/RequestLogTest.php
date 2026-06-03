<?php

use App\Models\License;
use App\Models\RequestLog;
use App\Models\Website;
use Illuminate\Support\Facades\Schema;

test('request logs table has the expected columns', function () {
    expect(Schema::hasColumns('request_logs', [
        'id',
        'route',
        'method',
        'request',
        'status',
        'website_id',
        'license_id',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('a request log belongs to a website and license', function () {
    $website = Website::factory()->create();
    $license = License::factory()->create();

    $requestLog = RequestLog::factory()->create([
        'route' => '/api/validate-license',
        'method' => 'POST',
        'request' => ['license_key' => 'APICO-TEST-LICENSE'],
        'status' => 200,
        'website_id' => $website->id,
        'license_id' => $license->id,
    ]);

    expect($requestLog->website)
        ->toBeInstanceOf(Website::class)
        ->id->toBe($website->id)
        ->and($requestLog->license)
        ->toBeInstanceOf(License::class)
        ->id->toBe($license->id)
        ->and($requestLog->request)
        ->toBe(['license_key' => 'APICO-TEST-LICENSE'])
        ->and($requestLog->status)
        ->toBe(200);
});
