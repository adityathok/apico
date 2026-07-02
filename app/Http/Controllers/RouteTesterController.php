<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class RouteTesterController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $routes = collect(Route::getRoutes())->map(function ($route) {
            return [
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'middleware' => $route->middleware(),
                'action' => $route->getActionName(),
            ];
        })->filter(function ($route) {
            $uri = $route['uri'];

            return str_starts_with($uri, 'api/v1')
                || $uri === 'api';
        })->values();

        $groupedRoutes = $routes->groupBy(function ($route) {
            $parts = explode('/', $route['uri']);

            return $parts[0] . (isset($parts[1]) ? '/' . $parts[1] : '');
        })->toArray();

        $routePrefixes = array_keys($groupedRoutes);

        return Inertia::render('RouteTesters', [
            'routes' => $routes->toArray(),
            'groupedRoutes' => $groupedRoutes,
            'routePrefixes' => $routePrefixes,
            'todaySignature' => md5(now()->format('dmY')),
            'sampleProjectSlug' => Project::query()->value('slug'),
        ]);
    }
}
