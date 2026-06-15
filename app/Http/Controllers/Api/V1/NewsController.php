<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class NewsController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->withCount('posts')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'count' => $category->posts_count,
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => $categories,
        ]);
    }
}
