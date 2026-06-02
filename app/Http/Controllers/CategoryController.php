<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()
                ->withCount('posts')
                ->latest()
                ->paginate(),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): CategoryResource
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:categories,slug'],
            'description' => ['nullable', 'string'],
            'post_ids' => ['nullable', 'array'],
            'post_ids.*' => ['integer', 'exists:posts,id'],
        ]);

        $category = Category::create(Arr::except($validated, ['post_ids']));
        $category->posts()->sync($validated['post_ids'] ?? []);

        return CategoryResource::make($category->load('posts:id,title,slug'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): CategoryResource
    {
        return CategoryResource::make($category->load('posts:id,title,slug'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Category $category): CategoryResource
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string'],
            'post_ids' => ['nullable', 'array'],
            'post_ids.*' => ['integer', 'exists:posts,id'],
        ]);

        $category->update(Arr::except($validated, ['post_ids']));

        if ($request->has('post_ids')) {
            $category->posts()->sync($validated['post_ids'] ?? []);
        }

        return CategoryResource::make($category->load('posts:id,title,slug'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category): Response
    {
        $category->delete();

        return response()->noContent();
    }
}
