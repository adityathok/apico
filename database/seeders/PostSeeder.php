<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var array<int, string>
     */
    private const UnsplashImages = [
        'https://images.unsplash.com/photo-1499750310107-5fef28a66643?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1486312338219-ce68d2c6f44d?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=1200&q=80',
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

            Post::factory(5)
                ->for($user)
                ->sequence(...array_map(
                    fn (string $image): array => ['image' => $image],
                    self::UnsplashImages,
                ))
                ->create();
        });
    }
}
