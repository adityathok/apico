<?php

namespace Database\Seeders;

use App\Models\Server;
use Illuminate\Database\Seeder;

class ServerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            [
                'server_ip' => '192.168.10.20',
                'server_domain' => 'api-primary.test',
                'server_name' => 'Primary API Server',
            ],
            [
                'server_ip' => '192.168.10.21',
                'server_domain' => 'worker-node.test',
                'server_name' => 'Worker Node Server',
            ],
            [
                'server_ip' => '192.168.10.22',
                'server_domain' => 'staging-api.test',
                'server_name' => 'Staging API Server',
            ],
        ])->each(function (array $server): void {
            Server::firstOrCreate(
                ['server_domain' => $server['server_domain']],
                $server,
            );
        });
    }
}
