<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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

        $project->update($validated);

        return ProjectResource::make($project->load('parent:id,name'));
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Project $project): HttpResponse
    {
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
            'package_file_url' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => array_values(array_filter([
                $isUpdate ? 'sometimes' : null,
                'required',
                Rule::in($supportedTypes),
            ])),
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('projects', 'id'),
                Rule::notIn($project ? [$project->id] : []),
            ],
        ];
    }
}
