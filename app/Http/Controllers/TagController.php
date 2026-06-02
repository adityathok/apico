<?php

namespace App\Http\Controllers;

use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection(
            Tag::query()
                ->withCount('posts')
                ->latest()
                ->paginate(),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): TagResource
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tags,slug'],
            'post_ids' => ['nullable', 'array'],
            'post_ids.*' => ['integer', 'exists:posts,id'],
        ]);

        $tag = Tag::create(Arr::except($validated, ['post_ids']));
        $tag->posts()->sync($validated['post_ids'] ?? []);

        return TagResource::make($tag->load('posts:id,title,slug'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Tag $tag): TagResource
    {
        return TagResource::make($tag->load('posts:id,title,slug'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tag $tag): TagResource
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('tags', 'slug')->ignore($tag)],
            'post_ids' => ['nullable', 'array'],
            'post_ids.*' => ['integer', 'exists:posts,id'],
        ]);

        $tag->update(Arr::except($validated, ['post_ids']));

        if ($request->has('post_ids')) {
            $tag->posts()->sync($validated['post_ids'] ?? []);
        }

        return TagResource::make($tag->load('posts:id,title,slug'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tag $tag): Response
    {
        $tag->delete();

        return response()->noContent();
    }
}
