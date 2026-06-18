<?php

use App\Models\License;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\LicenseSeeder;
use Illuminate\Support\Facades\Schema;

test('licenses table has the expected columns', function () {
    expect(Schema::hasColumns('licenses', [
        'id',
        'user_id',
        'code',
        'is_active',
        'used_at',
        'expires_at',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('a license can belong to a user', function () {
    $user = User::factory()->create();
    $license = License::factory()->for($user)->create();

    expect($license->user)
        ->toBeInstanceOf(User::class)
        ->id->toBe($user->id);
});

test('license attributes are cast correctly', function () {
    $license = License::factory()->create([
        'is_active' => false,
        'used_at' => now(),
        'expires_at' => now()->addYear(),
    ]);

    expect($license->is_active)->toBeFalse()
        ->and($license->used_at)->toBeInstanceOf(DateTimeInterface::class)
        ->and($license->expires_at)->toBeInstanceOf(DateTimeInterface::class);
});

test('license seeder creates license codes once', function () {
    $this->seed(LicenseSeeder::class);
    $this->seed(LicenseSeeder::class);

    expect(License::count())->toBe(10)
        ->and(License::where('code', 'APICO-2026-0001')->exists())->toBeTrue()
        ->and(License::where('is_active', true)->count())->toBe(10);
});

test('license endpoint updates website versions when request includes them', function () {
    $license = License::factory()->create([
        'code' => 'APICO-LICENSE-0001',
        'expires_at' => now()->addDay(),
    ]);
    $website = Website::factory()->create([
        'domain' => 'client.example.com',
        'license_key' => 'OLD-LICENSE',
        'plugin_version' => '1.0.0',
        'wp_version' => '6.4.0',
        'php_version' => '8.2',
    ]);

    $this->withHeaders([
        'License' => $license->code,
        'source' => $website->domain,
    ])->getJson('/api/v1/license?wp_version=6.6.1&php_version=8.3&velocity_addons_version=2.4.0')
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.website', $website->domain);

    $website->refresh();

    expect($website->license_key)->toBe($license->code)
        ->and($website->wp_version)->toBe('6.6.1')
        ->and($website->php_version)->toBe('8.3')
        ->and($website->plugin_version)->toBe('2.4.0');
});
