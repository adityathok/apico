<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            ['name' => 'Breaking News', 'slug' => 'breaking-news'],
            ['name' => 'Pemilu', 'slug' => 'pemilu'],
            ['name' => 'Pilkada', 'slug' => 'pilkada'],
            ['name' => 'Korupsi', 'slug' => 'korupsi'],
            ['name' => 'Kriminal', 'slug' => 'kriminal'],
            ['name' => 'Bursa Saham', 'slug' => 'bursa-saham'],
            ['name' => 'UMKM', 'slug' => 'umkm'],
            ['name' => 'Sepak Bola', 'slug' => 'sepak-bola'],
            ['name' => 'MotoGP', 'slug' => 'motogp'],
            ['name' => 'Film', 'slug' => 'film'],
            ['name' => 'Musik', 'slug' => 'musik'],
            ['name' => 'Kesehatan', 'slug' => 'kesehatan'],
            ['name' => 'Kuliner', 'slug' => 'kuliner'],
            ['name' => 'Travel', 'slug' => 'travel'],
            ['name' => 'Gadget', 'slug' => 'gadget'],
            ['name' => 'Startup', 'slug' => 'startup'],
        ])->each(function (array $tag): void {
            Tag::firstOrCreate(
                ['slug' => $tag['slug']],
                $tag,
            );
        });
    }
}
