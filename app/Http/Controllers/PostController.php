<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\UnsplashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'post_per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $posts = Post::query()
            ->with(['user:id,name,email', 'categories:id,name,slug', 'tags:id,name,slug'])
            ->when(
                array_key_exists('category_id', $validated) && $validated['category_id'] !== null,
                fn ($query) => $query->whereHas(
                    'categories',
                    fn ($categoryQuery) => $categoryQuery->whereKey($validated['category_id'])
                ),
            )
            ->latest()
            ->paginate($validated['post_per_page'] ?? 15)
            ->withQueryString();

        return PostResource::collection(
            $posts,
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): PostResource
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:posts,slug'],
            'image' => ['nullable', 'image', 'max:2048'],
            'image_caption' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'published_at' => ['nullable', 'date'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        $validated['slug'] = Str::slug($validated['slug']);

        if ($request->hasFile('image')) {
            $validated['image'] = $this->storeImage($request);
        }

        $post = Post::create(Arr::except($validated, ['category_ids', 'tag_ids']));
        $post->categories()->sync($validated['category_ids'] ?? []);
        $post->tags()->sync($validated['tag_ids'] ?? []);

        return PostResource::make($post->load(['user:id,name,email', 'categories:id,name,slug', 'tags:id,name,slug']));
    }

    /**
     * Display the specified resource.
     */
    public function show(Post $post): PostResource
    {
        return PostResource::make($post->load(['user:id,name,email', 'categories:id,name,slug', 'tags:id,name,slug']));
    }

    public function recommendedImages(Request $request, UnsplashService $unsplashService): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
            'orientation' => ['nullable', Rule::in(['landscape', 'portrait', 'squarish'])],
        ]);

        $currentPage = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 12);
        $orientation = $validated['orientation'] ?? 'landscape';

        $result = $unsplashService->searchPhotos(
            $validated['query'],
            $currentPage,
            $perPage,
            $orientation,
        );

        $images = collect($result['results'] ?? [])
            ->map(fn (array $photo): array => [
                'id' => $photo['id'] ?? null,
                'description' => $photo['description'] ?? $photo['alt_description'] ?? null,
                'thumb_url' => Arr::get($photo, 'urls.thumb'),
                'regular_url' => Arr::get($photo, 'urls.regular'),
                'author_name' => Arr::get($photo, 'user.name'),
            ])
            ->filter(fn (array $photo): bool => filled($photo['id']) && filled($photo['thumb_url']) && filled($photo['regular_url']))
            ->values();

        $total = max((int) ($result['total'] ?? 0), 0);
        $lastPage = max((int) ($result['total_pages'] ?? 1), 1);
        $from = $images->isEmpty() ? null : (($currentPage - 1) * $perPage) + 1;
        $to = $images->isEmpty() ? null : $from + $images->count() - 1;

        return response()->json([
            'data' => $images,
            'meta' => [
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function recommendedImage(Request $request): Response
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'file_name' => ['nullable', 'string', 'max:255'],
        ]);

        $host = (string) parse_url($validated['url'], PHP_URL_HOST);
        $allowedHosts = ['images.unsplash.com', 'plus.unsplash.com'];

        if (! in_array($host, $allowedHosts, true)) {
            throw ValidationException::withMessages([
                'url' => 'Only Unsplash image URLs are allowed.',
            ]);
        }

        $response = Http::accept('image/*')
            ->timeout(20)
            ->get($validated['url'])
            ->throw();

        $contentType = (string) $response->header('Content-Type');

        if (! str_starts_with($contentType, 'image/')) {
            throw ValidationException::withMessages([
                'url' => 'The selected URL did not return an image.',
            ]);
        }

        $extension = match ($contentType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };

        $fileName = Str::slug(
            pathinfo($validated['file_name'] ?? 'unsplash-image', PATHINFO_FILENAME),
        );

        if ($fileName === '') {
            $fileName = 'unsplash-image';
        }

        return response($response->body(), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => sprintf('inline; filename="%s.%s"', $fileName, $extension),
        ]);
    }

    /**
     * Display public post cards.
     */
    public function publicIndex(): View
    {
        $posts = Post::query()
            ->with(['user:id,name,email', 'categories:id,name,slug', 'tags:id,name,slug'])
            ->latest()
            ->paginate(9);

        $imageUrls = $posts
            ->getCollection()
            ->mapWithKeys(fn (Post $post): array => [
                $post->id => $this->imageUrl($post->image),
            ]);

        return view('posts.index', [
            'posts' => $posts,
            'imageUrls' => $imageUrls,
        ]);
    }

    /**
     * Display a public post page by slug.
     */
    public function read(string $slug): View
    {
        $post = Post::query()
            ->with(['user:id,name,email', 'categories:id,name,slug', 'tags:id,name,slug'])
            ->where('slug', $slug)
            ->firstOrFail();

        return view('posts.read', [
            'post' => $post,
            'imageUrl' => $this->imageUrl($post->image),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Post $post): PostResource
    {
        $validated = $request->validate([
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($post)],
            'image' => ['nullable', 'image', 'max:2048'],
            'image_caption' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['sometimes', 'required', 'string'],
            'published_at' => ['nullable', 'date'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

        if (array_key_exists('slug', $validated)) {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        if ($request->hasFile('image')) {
            $this->deleteImage($post);
            $validated['image'] = $this->storeImage($request);
        }

        $post->update(Arr::except($validated, ['category_ids', 'tag_ids']));

        if ($request->has('category_ids')) {
            $post->categories()->sync($validated['category_ids'] ?? []);
        }

        if ($request->has('tag_ids')) {
            $post->tags()->sync($validated['tag_ids'] ?? []);
        }

        return PostResource::make($post->load(['user:id,name,email', 'categories:id,name,slug', 'tags:id,name,slug']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Post $post): Response
    {
        $this->deleteImage($post);
        $post->delete();

        return response()->noContent();
    }

    private function storeImage(Request $request): string
    {
        return $request->file('image')->store('post/'.now()->format('y-m'), 'public');
    }

    private function deleteImage(Post $post): void
    {
        if ($post->image === null) {
            return;
        }

        Storage::disk('public')->delete($post->image);
    }

    private function imageUrl(?string $image): ?string
    {
        return match (true) {
            $image === null => null,
            str_starts_with($image, 'http') => $image,
            default => Storage::disk('public')->url($image),
        };
    }
}
