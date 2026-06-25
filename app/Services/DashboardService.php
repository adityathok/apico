<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\RequestLog;
use App\Models\Website;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Build the dashboard payload.
     *
     * @return array{
     *     totals: array{
     *         websites: int,
     *         request_logs_today: int,
     *         posts: int,
     *         request_logs_this_month: int,
     *         request_logs_period: int
     *     },
     *     request_logs_daily: array<int, array{
     *         date: string,
     *         label: string,
     *         total: int
     *     }>,
     *     request_logs_top_routes: array<int, array{
     *         route: string,
     *         total: int,
     *         percentage: float
     *     }>,
     *     top_categories_by_posts: array<int, array{
     *         name: string,
     *         total: int
     *     }>
     * }
     */
    public function getData(string $period = '30days'): array
    {
        $today = CarbonImmutable::today();
        [$startDate, $endDate] = $this->requestLogRange($today, $period);
        $dailyLogs = $this->requestLogsDaily($startDate, $endDate, $period);
        $topRoutes = $this->requestLogsTopRoutes($startDate, $endDate);

        return [
            'totals' => [
                'websites' => Website::query()->count(),
                'request_logs_today' => RequestLog::query()
                    ->whereDate('created_at', $today)
                    ->count(),
                'posts' => Post::query()->count(),
                'request_logs_this_month' => RequestLog::query()
                    ->whereBetween('created_at', [
                        $today->startOfMonth(),
                        $today->endOfMonth(),
                    ])
                    ->count(),
                'request_logs_period' => RequestLog::query()
                    ->whereBetween('created_at', [
                        $startDate->startOfDay(),
                        $endDate->endOfDay(),
                    ])
                    ->count(),
            ],
            'request_logs_daily' => $dailyLogs->values()->all(),
            'request_logs_top_routes' => $topRoutes->values()->all(),
            'top_categories_by_posts' => $this->topCategoriesByPosts()->values()->all(),
        ];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function requestLogRange(CarbonImmutable $today, string $period): array
    {
        return match ($period) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            '7days' => [$today->subDays(6), $today],
            default => [$today->subDays(29), $today],
        };
    }

    /**
     * @return Collection<int, array{date: string, label: string, total: int}>
     */
    private function requestLogsDaily(CarbonImmutable $startDate, CarbonImmutable $endDate, string $period): Collection
    {
        if (in_array($period, ['today', 'yesterday'], true)) {
            return $this->requestLogsHourly($startDate);
        }

        /** @var Collection<string, int> $dailyTotals */
        $dailyTotals = RequestLog::query()
            ->whereBetween('created_at', [
                $startDate->startOfDay(),
                $endDate->endOfDay(),
            ])
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->map(fn (mixed $total): int => (int) $total);

        return collect(range(0, $startDate->diffInDays($endDate)))
            ->map(function (int $offset) use ($startDate, $dailyTotals): array {
                $date = $startDate->addDays($offset);
                $dateKey = $date->toDateString();

                return [
                    'date' => $dateKey,
                    'label' => $date->format('d M'),
                    'total' => $dailyTotals->get($dateKey, 0),
                ];
            });
    }

    /**
     * @return Collection<int, array{date: string, label: string, total: int}>
     */
    private function requestLogsHourly(CarbonImmutable $date): Collection
    {
        /** @var Collection<string, int> $hourlyTotals */
        $hourlyTotals = RequestLog::query()
            ->whereBetween('created_at', [
                $date->startOfDay(),
                $date->endOfDay(),
            ])
            ->get('created_at')
            ->countBy(fn (RequestLog $requestLog): string => CarbonImmutable::parse($requestLog->created_at)->format('H'));

        return collect(range(0, 23))
            ->map(function (int $hour) use ($date, $hourlyTotals): array {
                $dateTime = $date->setTime($hour, 0);
                $hourKey = $dateTime->format('H');

                return [
                    'date' => $dateTime->format('Y-m-d\TH:00:00'),
                    'label' => $dateTime->format('H:00'),
                    'total' => $hourlyTotals->get($hourKey, 0),
                ];
            });
    }

    /**
     * @return Collection<int, array{route: string, total: int, percentage: float}>
     */
    private function requestLogsTopRoutes(CarbonImmutable $startDate, CarbonImmutable $endDate): Collection
    {
        $routes = RequestLog::query()
            ->whereBetween('created_at', [
                $startDate->startOfDay(),
                $endDate->endOfDay(),
            ])
            ->selectRaw('route, count(*) as total')
            ->groupBy('route')
            ->orderByDesc('total')
            ->orderBy('route')
            ->limit(5)
            ->get()
            ->map(fn (RequestLog $requestLog): array => [
                'route' => $requestLog->route,
                'total' => (int) $requestLog->getAttribute('total'),
            ]);

        $grandTotal = $routes->sum('total');

        return $routes->map(fn (array $route): array => [
            'route' => $route['route'],
            'total' => $route['total'],
            'percentage' => $grandTotal === 0
                ? 0.0
                : round(($route['total'] / $grandTotal) * 100, 2),
        ]);
    }

    /**
     * @return Collection<int, array{name: string, total: int}>
     */
    private function topCategoriesByPosts(): Collection
    {
        return Category::query()
            ->withCount('posts')
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn (Category $category): array => [
                'name' => $category->name,
                'total' => (int) $category->posts_count,
            ]);
    }
}
