<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display the projects admin page.
     */
    public function index(Request $request): Response
    {
        $projects = ProjectResource::collection(
            Project::query()
                ->with('parent:id,name')
                ->latest('id')
                ->paginate(),
        );

        return Inertia::render('Projects', [
            'projects' => $projects,
        ]);
    }
}
