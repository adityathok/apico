<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        collect([
            [
                'name' => 'Nasional',
                'slug' => 'nasional',
                'description' => 'Berita utama dari berbagai wilayah Indonesia.',
            ],
            [
                'name' => 'Politik',
                'slug' => 'politik',
                'description' => 'Kabar pemerintahan, partai politik, pemilu, dan kebijakan publik.',
            ],
            [
                'name' => 'Ekonomi',
                'slug' => 'ekonomi',
                'description' => 'Berita bisnis, keuangan, pasar, industri, dan ekonomi makro.',
            ],
            [
                'name' => 'Dunia',
                'slug' => 'dunia',
                'description' => 'Peristiwa internasional dan kabar global terkini.',
            ],
            [
                'name' => 'Hukum',
                'slug' => 'hukum',
                'description' => 'Berita hukum, kriminal, pengadilan, dan penegakan aturan.',
            ],
            [
                'name' => 'Olahraga',
                'slug' => 'olahraga',
                'description' => 'Kabar pertandingan, atlet, liga, dan turnamen olahraga.',
            ],
            [
                'name' => 'Teknologi',
                'slug' => 'teknologi',
                'description' => 'Perkembangan teknologi, startup, gawai, dan inovasi digital.',
            ],
            [
                'name' => 'Hiburan',
                'slug' => 'hiburan',
                'description' => 'Berita selebritas, film, musik, televisi, dan budaya populer.',
            ],
            [
                'name' => 'Lifestyle',
                'slug' => 'lifestyle',
                'description' => 'Gaya hidup, kesehatan, perjalanan, kuliner, dan keluarga.',
            ],
            [
                'name' => 'Otomotif',
                'slug' => 'otomotif',
                'description' => 'Berita kendaraan, industri otomotif, review, dan tips berkendara.',
            ],
        ])->each(function (array $category): void {
            Category::firstOrCreate(
                ['slug' => $category['slug']],
                $category,
            );
        });
    }
}
