<?php

use App\Models\Website;
use Illuminate\Support\Facades\Schema;

test('websites table has the expected columns', function () {
    expect(Schema::hasColumns('websites', [
        'id',
        'domain',
        'ip_address',
        'license_key',
        'status',
        'theme_version',
        'plugin_version',
        'wp_version',
        'php_version',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('a website can be created with version details', function () {
    $website = Website::factory()->create([
        'domain' => 'example.test',
        'ip_address' => '192.168.1.10',
        'license_key' => 'APICO-TEST-LICENSE',
        'status' => 'active',
        'theme_version' => '1.2.3',
        'plugin_version' => '4.5.6',
        'wp_version' => '6.5.4',
        'php_version' => '8.3',
    ]);

    expect($website)
        ->domain->toBe('example.test')
        ->ip_address->toBe('192.168.1.10')
        ->license_key->toBe('APICO-TEST-LICENSE')
        ->status->toBe('active')
        ->theme_version->toBe('1.2.3')
        ->plugin_version->toBe('4.5.6')
        ->wp_version->toBe('6.5.4')
        ->php_version->toBe('8.3');
});
