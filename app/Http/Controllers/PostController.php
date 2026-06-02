<?php

namespace App\Http\Controllers;

use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return PostResource::collection(
            Post::query()
                ->with(['user:id,name,email', 'categories:id,name,slug', 'tags:id,name,slug'])
                ->latest()
                ->paginate(),
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
            'image' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'published_at' => ['nullable', 'date'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

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
     * Update the specified resource in storage.
     */
    public function update(Request $request, Post $post): PostResource
    {
        $validated = $request->validate([
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($post)],
            'image' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['sometimes', 'required', 'string'],
            'published_at' => ['nullable', 'date'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
        ]);

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
        $post->delete();

        return response()->noContent();
    }
}
