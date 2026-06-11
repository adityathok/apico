<?php

use App\Models\License;
use App\Models\RequestLog;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\RequestLogSeeder;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

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

test('authenticated users can view request logs from the controller', function () {
    $user = User::factory()->create();
    $requestLog = RequestLog::factory()->create([
        'route' => '/api/validate-license',
        'method' => 'POST',
        'status' => 200,
    ]);

    $this->actingAs($user)
        ->get(route('requestlogsIndex'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('RequestLogs')
            ->has('requestLogs.data', 1)
            ->where('requestLogs.data.0.id', $requestLog->id)
            ->where('requestLogs.data.0.route', '/api/validate-license')
            ->where('requestLogs.data.0.method', 'POST')
            ->where('requestLogs.data.0.status', 200)
            ->has('requestLogs.data.0.website')
            ->has('requestLogs.data.0.license')
            ->where('requestLogs.meta.total', 1));
});

test('request log seeder creates request logs once', function () {
    $this->seed(RequestLogSeeder::class);
    $this->seed(RequestLogSeeder::class);

    expect(RequestLog::count())->toBe(3)
        ->and(RequestLog::where('route', '/api/validate-license')->count())->toBe(2)
        ->and(RequestLog::where('route', '/api/check-update')->count())->toBe(1)
        ->and(RequestLog::where('status', 403)->count())->toBe(1);
});
