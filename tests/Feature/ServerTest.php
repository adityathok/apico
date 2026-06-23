<?php

use App\Models\Server;
use Database\Seeders\ServerSeeder;
use Illuminate\Support\Facades\Schema;

test('servers table has the expected columns', function () {
    expect(Schema::hasColumns('servers', [
        'id',
        'server_ip',
        'server_domain',
        'server_name',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

test('a server can be created with the expected details', function () {
    $server = Server::factory()->create([
        'server_ip' => '192.168.10.25',
        'server_domain' => 'api.example.test',
        'server_name' => 'Primary API Server',
    ]);

    expect($server)
        ->server_ip->toBe('192.168.10.25')
        ->server_domain->toBe('api.example.test')
        ->server_name->toBe('Primary API Server');
});

test('server seeder creates servers once', function () {
    $this->seed(ServerSeeder::class);
    $this->seed(ServerSeeder::class);

    expect(Server::count())->toBe(3)
        ->and(Server::where('server_domain', 'api-primary.test')->exists())->toBeTrue()
        ->and(Server::where('server_name', 'Primary API Server')->exists())->toBeTrue()
        ->and(Server::where('server_ip', '192.168.10.22')->exists())->toBeTrue();
});
