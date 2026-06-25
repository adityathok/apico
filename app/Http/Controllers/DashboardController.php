<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(public DashboardService $dashboardService) {}

    public function __invoke(Request $request): Response
    {
        $period = $request->string('period')->toString();
        $period = in_array($period, ['today', 'yesterday', '7days', '30days'], true)
            ? $period
            : '30days';

        return Inertia::render('Dashboard', [
            'dashboardData' => $this->dashboardService->getData($period),
            'filters' => [
                'period' => $period,
            ],
        ]);
    }
}
