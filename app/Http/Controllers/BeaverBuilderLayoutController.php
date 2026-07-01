<?php

namespace App\Http\Controllers;

use App\Http\Resources\BeaverBuilderLayoutResource;
use App\Models\BeaverBuilderLayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BeaverBuilderLayoutController extends Controller
{
    private const array THEME_LAYOUT_TYPES = ['header', 'footer', 'archive', 'singular', '404', 'part'];

    public function index(): AnonymousResourceCollection
    {
        return BeaverBuilderLayoutResource::collection(
            BeaverBuilderLayout::with('categories')
                ->latest()
                ->paginate(),
        );
    }

    public function store(Request $request): BeaverBuilderLayoutResource
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['theme-layout', 'template-layout', 'row', 'module'])],
            'theme_layout_type' => ['nullable', 'string', Rule::in(self::THEME_LAYOUT_TYPES)],
            'content' => ['required', 'string'],
            'meta' => ['nullable'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:beaver_builder_template_categories,id'],
        ]);

        if ($validated['type'] !== 'theme-layout') {
            $validated['theme_layout_type'] = null;
        }

        $validated['meta'] = $this->decodeMeta($validated['meta'] ?? null);

        $screenshot = $this->handleScreenshotUpload($request);
        if ($screenshot !== null) {
            $validated['screenshot'] = $screenshot;
        }

        $layout = BeaverBuilderLayout::create(Arr::except($validated, ['category_ids']));
        $layout->categories()->sync($validated['category_ids'] ?? []);

        return BeaverBuilderLayoutResource::make($layout->load('categories'));
    }

    public function show(BeaverBuilderLayout $beaverBuilderLayout): BeaverBuilderLayoutResource
    {
        return BeaverBuilderLayoutResource::make($beaverBuilderLayout->load('categories'));
    }

    public function update(Request $request, BeaverBuilderLayout $beaverBuilderLayout): BeaverBuilderLayoutResource
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(['theme-layout', 'template-layout', 'row', 'module'])],
            'theme_layout_type' => ['nullable', 'string', Rule::in(self::THEME_LAYOUT_TYPES)],
            'content' => ['sometimes', 'required', 'string'],
            'meta' => ['nullable'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:beaver_builder_template_categories,id'],
        ]);

        if (isset($validated['type']) && $validated['type'] !== 'theme-layout') {
            $validated['theme_layout_type'] = null;
        }

        $validated['meta'] = $this->decodeMeta($validated['meta'] ?? null);

        $screenshot = $this->handleScreenshotUpload($request, $beaverBuilderLayout->screenshot);
        if ($screenshot !== null) {
            $validated['screenshot'] = $screenshot;
        }

        $beaverBuilderLayout->update(Arr::except($validated, ['category_ids']));

        if ($request->has('category_ids')) {
            $beaverBuilderLayout->categories()->sync($validated['category_ids'] ?? []);
        }

        return BeaverBuilderLayoutResource::make($beaverBuilderLayout->load('categories'));
    }

    public function destroy(BeaverBuilderLayout $beaverBuilderLayout): Response
    {
        if (
            $beaverBuilderLayout->screenshot
            && ! Str::startsWith($beaverBuilderLayout->screenshot, ['http://', 'https://'])
        ) {
            Storage::disk('public')->delete($beaverBuilderLayout->screenshot);
        }

        $beaverBuilderLayout->delete();

        return response()->noContent();
    }

    private function handleScreenshotUpload(Request $request, ?string $existingPath = null): ?string
    {
        if ($request->hasFile('screenshot')) {
            if (
                $existingPath
                && ! Str::startsWith($existingPath, ['http://', 'https://'])
            ) {
                Storage::disk('public')->delete($existingPath);
            }

            $file = $request->file('screenshot');
            $filename = Str::random(40).'.'.$file->getClientOriginalExtension();

            return $file->storeAs('beaver-layouts-screenshots', $filename, 'public');
        }

        if ($request->boolean('remove_screenshot')) {
            if (
                $existingPath
                && ! Str::startsWith($existingPath, ['http://', 'https://'])
            ) {
                Storage::disk('public')->delete($existingPath);
            }

            return '';
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
