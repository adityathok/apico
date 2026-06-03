<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class PostSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, array{title: string, background: string, accent: string}>
     */
    private const SeedImages = [
        ['title' => 'Berita Utama', 'background' => '#0f172a', 'accent' => '#38bdf8'],
        ['title' => 'Liputan Nasional', 'background' => '#14532d', 'accent' => '#86efac'],
        ['title' => 'Ekonomi Hari Ini', 'background' => '#7c2d12', 'accent' => '#fdba74'],
        ['title' => 'Olahraga', 'background' => '#1e3a8a', 'accent' => '#93c5fd'],
        ['title' => 'Teknologi', 'background' => '#581c87', 'accent' => '#d8b4fe'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::whereIn('email', [
            'admin@example.com',
            'test@example.com',
        ])->get();

        if ($users->isEmpty()) {
            $users = User::factory(2)->create();
        }

        $users->each(function (User $user): void {
            if (Post::whereBelongsTo($user)->exists()) {
                return;
            }

            $imagePaths = $this->seedImagePaths($user);

            Post::factory(5)
                ->for($user)
                ->sequence(...array_map(
                    fn (string $image): array => ['image' => $image],
                    $imagePaths,
                ))
                ->create();
        });
    }

    /**
     * @return array<int, string>
     */
    private function seedImagePaths(User $user): array
    {
        return collect(self::SeedImages)
            ->map(function (array $image, int $index) use ($user): string {
                $path = 'post/'.now()->format('y-m').'/seed-post-'.$user->id.'-'.($index + 1).'.svg';

                Storage::disk('public')->put(
                    $path,
                    $this->seedImageSvg($image['title'], $image['background'], $image['accent']),
                );

                return $path;
            })
            ->all();
    }

    private function seedImageSvg(string $title, string $background, string $accent): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="675" viewBox="0 0 1200 675" role="img" aria-label="{$safeTitle}">
  <rect width="1200" height="675" fill="{$background}"/>
  <rect x="72" y="72" width="1056" height="531" rx="28" fill="none" stroke="{$accent}" stroke-width="6"/>
  <circle cx="156" cy="156" r="34" fill="{$accent}"/>
  <text x="72" y="382" fill="#ffffff" font-family="Arial, Helvetica, sans-serif" font-size="76" font-weight="700">{$safeTitle}</text>
  <text x="72" y="454" fill="{$accent}" font-family="Arial, Helvetica, sans-serif" font-size="34" font-weight="600">APICO News Portal</text>
</svg>
SVG;
    }
}
