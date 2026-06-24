<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectChangelogResource;
use App\Models\ProjectChangelog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ProjectChangelogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $validated = request()->validate([
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return ProjectChangelogResource::collection(
            ProjectChangelog::query()
                ->when(
                    $validated['project_id'] ?? null,
                    fn ($query, int $projectId) => $query->where('project_id', $projectId),
                )
                ->with('project:id,name,slug')
                ->latest()
                ->paginate($validated['per_page'] ?? 15),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): ProjectChangelogResource
    {
        $validated = $request->validate($this->rules());

        $projectChangelog = ProjectChangelog::create($validated);

        return ProjectChangelogResource::make($projectChangelog->load('project:id,name,slug'));
    }

    /**
     * Display the specified resource.
     */
    public function show(ProjectChangelog $projectChangelog): ProjectChangelogResource
    {
        return ProjectChangelogResource::make($projectChangelog->load('project:id,name,slug'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProjectChangelog $projectChangelog): ProjectChangelogResource
    {
        $validated = $request->validate($this->rules($projectChangelog, true));

        $projectChangelog->update($validated);

        return ProjectChangelogResource::make($projectChangelog->load('project:id,name,slug'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectChangelog $projectChangelog): Response
    {
        $projectChangelog->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?ProjectChangelog $projectChangelog = null, bool $isUpdate = false): array
    {
        return [
            'project_id' => array_values(array_filter([
                $isUpdate ? 'sometimes' : null,
                'required',
                'integer',
                Rule::exists('projects', 'id'),
            ])),
            'project_version' => array_values(array_filter([
                $isUpdate ? 'sometimes' : null,
                'required',
                'string',
                'max:255',
            ])),
            'changelog_content' => array_values(array_filter([
                $isUpdate ? 'sometimes' : null,
                'required',
                'string',
            ])),
        ];
    }
}
