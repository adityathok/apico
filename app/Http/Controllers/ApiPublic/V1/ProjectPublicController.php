<?php

namespace App\Http\Controllers\ApiPublic\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectPublicController extends Controller
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

        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => ProjectResource::make($project)->resolve($request),
        ]);
    }
}
