<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\GithubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function show(Request $request, string $slug): JsonResponse
    {
        $project = Project::query()
            ->with('parent:id,name')
            ->where('slug', $slug)
            ->first();

        if (! $project instanceof Project) {
            return response()->json([
                'status' => false,
                'message' => 'Project not found',
            ], 404);
        }

        return $this->projectResponse($request, $project);
    }

    public function syncGithubRelease(Request $request, string $slug, GithubService $githubService): JsonResponse
    {
        $project = Project::query()
            ->with('parent:id,name')
            ->where('slug', $slug)
            ->first();

        if (! $project instanceof Project) {
            return response()->json([
                'status' => false,
                'message' => 'Project not found',
            ], 404);
        }

        if (blank($project->github_url)) {
            return response()->json([
                'status' => false,
                'message' => 'Project must have a GitHub URL before syncing releases.',
            ], 422);
        }

        $syncedProject = $githubService->syncGithubProjectRelease($project->id);

        if (! $syncedProject instanceof Project) {
            return response()->json([
                'status' => false,
                'message' => $githubService->lastSyncError() ?? 'Unable to sync GitHub release for project ID '.$project->id.'.',
            ], 422);
        }

        return $this->projectResponse($request, $syncedProject->load('parent:id,name'));
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $project = Project::query()
            ->with('parent:id,name')
            ->where('slug', $slug)
            ->first();

        if (! $project instanceof Project) {
            return response()->json([
                'status' => false,
                'message' => 'Project not found',
            ], 404);
        }

        $validated = $request->validate($this->rules($project));

        if (array_key_exists('slug', $validated)) {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        $validated = $this->normalizeRequiredVersions($validated, $project);

        if ($request->hasFile('package_file')) {
            $this->deletePackageFile($project);
            $validated['package_file'] = $this->storePackageFile($request);
        }

        $project->update($validated);

        return $this->projectResponse($request, $project->fresh()->load('parent:id,name'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(Project $project): array
    {
        $supportedTypes = [
            'project_internal',
            'project_client',
            'wp_theme',
            'wp_plugin',
            'wp_theme_child',
        ];

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('projects', 'slug')->ignore($project),
            ],
            'version' => ['nullable', 'string', 'max:255'],
            'requires_wp' => ['nullable', 'string', 'max:255'],
            'requires_php' => ['nullable', 'string', 'max:255'],
            'plugin_wp_required' => ['nullable', 'boolean'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'package_external_url' => ['nullable', 'url', 'max:255'],
            'icon' => ['nullable', 'string', 'max:255'],
            'screenshot' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'required', Rule::in($supportedTypes)],
            'package_file' => ['nullable', 'file', 'mimes:zip', 'max:51200'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id'),
                Rule::notIn([$project->id]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeRequiredVersions(array $validated, Project $project): array
    {
        $type = $validated['type'] ?? $project->type;

        if (! $this->isWordPressType($type)) {
            $validated['requires_wp'] = null;
            $validated['requires_php'] = null;
            $validated['plugin_wp_required'] = null;

            return $validated;
        }

        $validated['requires_wp'] = $validated['requires_wp'] ?? $project->requires_wp;
        $validated['requires_php'] = $validated['requires_php'] ?? $project->requires_php;
        $validated['plugin_wp_required'] = $this->isPluginType($type)
            ? ($validated['plugin_wp_required'] ?? $project->plugin_wp_required ?? false)
            : null;

        return $validated;
    }

    private function isPluginType(mixed $type): bool
    {
        return $type === 'wp_plugin';
    }

    private function isWordPressType(mixed $type): bool
    {
        return in_array($type, ['wp_theme', 'wp_plugin', 'wp_theme_child'], true);
    }

    private function storePackageFile(Request $request): ?string
    {
        if (! $request->hasFile('package_file')) {
            return null;
        }

        $name = $request->input('name');
        $version = $request->input('version');
        $file = $request->file('package_file');
        $fileName = Str::slug($name).'-'.Str::slug($version).'.'.$file->getClientOriginalExtension();
        $folder = 'project-packages/'.Str::slug($name);

        return $file->storeAs($folder, $fileName, 'public');
    }

    private function deletePackageFile(Project $project): void
    {
        if ($project->package_file === null || str_starts_with($project->package_file, 'http')) {
            return;
        }

        Storage::disk('public')->delete($project->package_file);
    }

    private function projectResponse(Request $request, Project $project): JsonResponse
    {
        $data = ProjectResource::make($project)->resolve($request);
        $downloadUrl = $data['package_external_url'] ?: $data['package_file_url'];
        $changelogUrl = url('project/changelog/'.$project->slug);

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => array_merge(
                Arr::except($data, ['created_at', 'updated_at']),
                ['download_url' => $downloadUrl, 'details_url' => $changelogUrl],
            ),
        ]);
    }
}
