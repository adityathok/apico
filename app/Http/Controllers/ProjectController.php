<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\GithubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProjectController extends Controller
{
    /**
     * Display the projects admin page.
     */
    public function index(): InertiaResponse
    {
        $projects = ProjectResource::collection(
            Project::query()
                ->with('parent:id,name')
                ->latest('id')
                ->paginate(),
        );

        return Inertia::render('Projects', [
            'projects' => $projects,
            'parentProjects' => Project::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request): ProjectResource
    {
        $validated = $request->validate($this->rules());
        $validated['slug'] = Str::slug($validated['slug']);
        $validated = $this->normalizeRequiredVersions($validated);
        $validated['package_file'] = $this->storePackageFile($request);
        unset($validated['remove_package_file']);

        $project = Project::create($validated);

        return ProjectResource::make($project->load('parent:id,name'));
    }

    /**
     * Display the specified project.
     */
    public function show(Project $project): ProjectResource
    {
        return ProjectResource::make($project->load('parent:id,name'));
    }

    public function syncGithubRelease(Project $project, GithubService $githubService): JsonResponse
    {
        if (blank($project->github_url)) {
            return response()->json([
                'message' => 'Project must have a GitHub URL before syncing releases.',
            ], 422);
        }

        $syncedProject = $githubService->syncGithubProjectRelease($project->id);

        if (! $syncedProject) {
            return response()->json([
                'message' => $githubService->lastSyncError() ?? 'Unable to sync GitHub release for project ID '.$project->id.'.',
            ], 422);
        }

        return response()->json([
            'message' => 'GitHub release synced successfully.',
            'data' => ProjectResource::make($syncedProject->load('parent:id,name'))->resolve(),
        ]);
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, Project $project): ProjectResource
    {
        $validated = $request->validate($this->rules($project, true));
        $shouldRemovePackageFile = $request->boolean('remove_package_file');

        if (array_key_exists('slug', $validated)) {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        $validated = $this->normalizeRequiredVersions($validated, $project);

        if ($request->hasFile('package_file')) {
            $this->deletePackageFile($project);
            $validated['package_file'] = $this->storePackageFile($request);
        } elseif ($shouldRemovePackageFile) {
            $this->deletePackageFile($project);
            $validated['package_file'] = null;
        }

        unset($validated['remove_package_file']);
        $project->update($validated);

        return ProjectResource::make($project->load('parent:id,name'));
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Project $project): HttpResponse
    {
        $this->deletePackageFile($project);
        $project->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?Project $project = null, bool $isUpdate = false): array
    {
        $supportedTypes = [
            'project_internal',
            'project_client',
            'wp_theme',
            'wp_plugin',
            'wp_theme_child',
        ];

        return [
            'name' => array_values(array_filter([
                $isUpdate ? 'sometimes' : null,
                'required',
                'string',
                'max:255',
            ])),
            'slug' => array_values(array_filter([
                $isUpdate ? 'sometimes' : null,
                'required',
                'string',
                'max:255',
                Rule::unique('projects', 'slug')->ignore($project),
            ])),
            'version' => ['nullable', 'string', 'max:255'],
            'requires_wp' => ['nullable', 'string', 'max:255'],
            'requires_php' => ['nullable', 'string', 'max:255'],
            'plugin_wp_required' => ['nullable', 'boolean'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'package_external_url' => ['nullable', 'url', 'max:255'],
            'remove_package_file' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'type' => array_values(array_filter([
                $isUpdate ? 'sometimes' : null,
                'required',
                Rule::in($supportedTypes),
            ])),
            'package_file' => array_values(array_filter([
                $isUpdate ? 'nullable' : 'required_without:package_external_url',
                'file',
                'mimes:zip',
                'max:51200',
            ])),
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id'),
                Rule::notIn($project ? [$project->id] : []),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeRequiredVersions(array $validated, ?Project $project = null): array
    {
        $type = $validated['type'] ?? $project?->type;

        if (! $this->isWordPressType($type)) {
            $validated['requires_wp'] = null;
            $validated['requires_php'] = null;
            $validated['plugin_wp_required'] = null;

            return $validated;
        }

        $validated['requires_wp'] = $validated['requires_wp'] ?? null;
        $validated['requires_php'] = $validated['requires_php'] ?? null;
        $validated['plugin_wp_required'] = $this->isPluginType($type)
            ? ($validated['plugin_wp_required'] ?? false)
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

        // 1. Ambil file dari request
        $file = $request->file('package_file');

        // 2. Tentukan nama file baru (contoh: nama-package-v1.0.0.zip)
        $fileName = Str::slug($name).'-'.Str::slug($version).'.'.$file->getClientOriginalExtension();

        // 3. Tentukan folder tujuan
        $folder = 'project-packages/'.Str::slug($name);

        // 4. Simpan dengan nama baru menggunakan storeAs
        $path = $file->storeAs($folder, $fileName, 'public');

        return $path;
    }

    private function deletePackageFile(Project $project): void
    {
        if ($project->package_file === null || str_starts_with($project->package_file, 'http')) {
            return;
        }

        Storage::disk('public')->delete($project->package_file);
    }
}
