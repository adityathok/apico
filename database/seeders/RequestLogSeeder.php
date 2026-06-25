<?php

namespace Database\Seeders;

use App\Models\License;
use App\Models\RequestLog;
use App\Models\Website;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RequestLogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            LicenseSeeder::class,
            WebsiteSeeder::class,
        ]);

        collect([
            [
                'domain' => 'apico-demo.test',
                'license_key' => 'APICO-2026-0001',
                'route' => '/api/validate-license',
                'method' => 'POST',
                'request' => ['domain' => 'apico-demo.test', 'license_key' => 'APICO-2026-0001'],
                'status' => 200,
            ],
            [
                'domain' => 'client-one.test',
                'license_key' => 'APICO-2026-0002',
                'route' => '/api/check-update',
                'method' => 'GET',
                'request' => ['domain' => 'client-one.test', 'plugin_version' => '1.2.0'],
                'status' => 200,
            ],
            [
                'domain' => 'expired-client.test',
                'license_key' => 'APICO-2026-0003',
                'route' => '/api/validate-license',
                'method' => 'POST',
                'request' => ['domain' => 'expired-client.test', 'license_key' => 'APICO-2026-0003'],
                'status' => 403,
            ],
        ])->each(function (array $requestLog): void {
            $website = Website::where('domain', $requestLog['domain'])->firstOrFail();
            $license = License::where('code', $requestLog['license_key'])->firstOrFail();

            RequestLog::firstOrCreate(
                [
                    'route' => $requestLog['route'],
                    'method' => $requestLog['method'],
                    'status' => $requestLog['status'],
                    'website_id' => $website->id,
                    'license_id' => $license->id,
                ],
                [
                    'request' => $requestLog['request'],
                ],
            );
        });

        $websites = Website::query()->get();
        $licenses = License::query()->get();
        $remaining = max(1000 - RequestLog::query()->count(), 0);

        RequestLog::factory()
            ->count($remaining)
            ->state(function () use ($websites, $licenses): array {
                $website = $websites->random();
                $license = $licenses->random();
                $route = fake()->randomElement(['/api/validate-license', '/api/check-update', '/api/download']);
                $createdAt = fake()->dateTimeBetween('-30 days');

                return [
                    'route' => $route,
                    'method' => $route === '/api/check-update' ? 'GET' : 'POST',
                    'request' => [
                        'domain' => $website->domain,
                        'license_key' => $license->code,
                        'plugin_version' => fake()->randomElement(['1.0.0', '1.1.0', '1.2.0', '2.0.0']),
                    ],
                    'status' => fake()->randomElement([200, 200, 200, 401, 403, 422]),
                    'website_id' => $website->id,
                    'license_id' => $license->id,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            })
            ->create();
    }
}
