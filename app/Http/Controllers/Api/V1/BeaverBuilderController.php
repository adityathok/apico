<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BeaverBuilderLayoutResource;
use App\Http\Resources\BeaverBuilderTemplateCategoryResource;
use App\Models\BeaverBuilderLayout;
use App\Models\BeaverBuilderTemplateCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BeaverBuilderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:theme-layout,template-layout,row,module'],
            'theme_layout_type' => ['nullable', 'string', 'in:header,footer,archive,singular,404,part'],
            'category_id' => ['nullable', 'integer', 'exists:beaver_builder_template_categories,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $layouts = BeaverBuilderLayout::query()
            ->with('categories')
            ->when(
                isset($validated['type']),
                fn (Builder $query): Builder => $query->where('type', $validated['type']),
            )
            ->when(
                isset($validated['theme_layout_type']),
                fn (Builder $query): Builder => $query->where('theme_layout_type', $validated['theme_layout_type']),
            )
            ->when(
                isset($validated['category_id']),
                fn (Builder $query): Builder => $query->whereHas(
                    'categories',
                    fn (Builder $q): Builder => $q->whereKey($validated['category_id']),
                ),
            )
            ->latest()
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => BeaverBuilderLayoutResource::collection($layouts->items())->resolve($request),
            'pagination' => [
                'current_page' => $layouts->currentPage(),
                'last_page' => $layouts->lastPage(),
                'per_page' => $layouts->perPage(),
                'total' => $layouts->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['theme-layout', 'template-layout', 'row', 'module'])],
            'theme_layout_type' => ['nullable', 'string', Rule::in(['header', 'footer', 'archive', 'singular', '404', 'part'])],
            'content' => ['required', 'string'],
            'meta' => ['nullable'],
            'screenshot' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:beaver_builder_template_categories,id'],
        ]);

        if ($validated['type'] !== 'theme-layout') {
            $validated['theme_layout_type'] = null;
        }

        $validated['meta'] = $this->decodeMeta($validated['meta'] ?? null);
        $validated['screenshot'] = $this->handleScreenshotUpload($request);

        $layout = BeaverBuilderLayout::create(Arr::except($validated, ['category_ids']));
        $layout->categories()->sync($validated['category_ids'] ?? []);

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => BeaverBuilderLayoutResource::make(
                $layout->load('categories'),
            )->resolve($request),
        ], 201);
    }

    public function show(Request $request, BeaverBuilderLayout $beaverBuilderLayout): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => BeaverBuilderLayoutResource::make(
                $beaverBuilderLayout->load('categories'),
            )->resolve($request),
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = BeaverBuilderTemplateCategory::query()
            ->with('layouts.categories')
            ->withCount('layouts')
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => BeaverBuilderTemplateCategoryResource::collection($categories)->resolve($request),
        ]);
    }

    private function handleScreenshotUpload(Request $request): ?string
    {
        if ($request->hasFile('screenshot')) {
            $file = $request->file('screenshot');
            $filename = Str::random(40).'.'.$file->getClientOriginalExtension();

            return $file->storeAs('beaver-layouts-screenshots', $filename, 'public');
        }

        return null;
    }

    private function decodeMeta(mixed $meta): ?array
    {
        if ($meta === null || $meta === '') {
            return null;
        }

        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
