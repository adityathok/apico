<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
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
        $validated['package_file_url'] = $this->storePackageFile($request);

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

    /**
     * Update the specified project.
     */
    public function update(Request $request, Project $project): ProjectResource
    {
        $validated = $request->validate($this->rules($project, true));

        if ($request->hasFile('package_file')) {
            $this->deletePackageFile($project);
            $validated['package_file_url'] = $this->storePackageFile($request);
        }

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
            'version' => ['nullable', 'string', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => array_values(array_filter([
                $isUpdate ? 'sometimes' : null,
                'required',
                Rule::in($supportedTypes),
            ])),
            'package_file' => array_values(array_filter([
                $isUpdate ? 'nullable' : 'required',
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

    private function storePackageFile(Request $request): string
    {
        $name = $request->input('name');
        $version = $request->input('version');

        // 1. Ambil file dari request
        $file = $request->file('package_file');

        // 2. Tentukan nama file baru (contoh: nama-package-v1.0.0.zip)
        $fileName = Str::slug($name) . '-' . Str::slug($version) . '.' . $file->getClientOriginalExtension();

        // 3. Tentukan folder tujuan
        $folder = 'project-packages/' . Str::slug($name);

        // 4. Simpan dengan nama baru menggunakan storeAs
        $path = $file->storeAs($folder, $fileName, 'public');

        return Storage::disk('public')->url($path);
    }

    private function deletePackageFile(Project $project): void
    {
        if ($project->package_file_url === null || ! str_contains($project->package_file_url, '/storage/')) {
            return;
        }

        $path = ltrim((string) parse_url($project->package_file_url, PHP_URL_PATH), '/');
        $path = preg_replace('#^storage/#', '', $path);

        if (! is_string($path) || $path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
