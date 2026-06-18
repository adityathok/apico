<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
                fn($query) => $query->whereHas(
                    'categories',
                    fn($categoryQuery) => $categoryQuery->whereKey($validated['category_id'])
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
            ->mapWithKeys(fn(Post $post): array => [
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
        return $request->file('image')->store('post/' . now()->format('y-m'), 'public');
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
