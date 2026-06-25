<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    VisAxis,
    VisDonut,
    VisGroupedBar,
    VisLine,
    VisScatter,
    VisScatterSelectors,
    VisSingleContainer,
    VisTooltip,
    VisXYContainer,
} from '@unovis/vue';
import { computed } from 'vue';
import { dashboard } from '@/routes';

type DashboardMetric = {
    label: string;
    value: number;
    tone: 'primary' | 'success' | 'warning' | 'neutral';
};

type DailyRequestLog = {
    date: string;
    label: string;
    total: number;
};

type TopRequestRoute = {
    route: string;
    total: number;
    percentage: number;
};

type PeriodFilter = 'today' | 'yesterday' | '7days' | '30days';

type DashboardData = {
    totals: {
        websites: number;
        request_logs_today: number;
        posts: number;
        request_logs_this_month: number;
        request_logs_period: number;
    };
    request_logs_daily: DailyRequestLog[];
    request_logs_top_routes: TopRequestRoute[];
    top_categories_by_posts: {
        name: string;
        total: number;
    }[];
};

const props = defineProps<{
    dashboardData: DashboardData;
    filters: {
        period: PeriodFilter;
    };
}>();

const chartColors = [
    'var(--color-chart-1)',
    'var(--color-chart-2)',
    'var(--color-chart-3)',
    'var(--color-chart-4)',
    'var(--color-chart-5)',
];

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const periodOptions: { label: string; value: PeriodFilter }[] = [
    { label: 'Today', value: 'today' },
    { label: 'Yesterday', value: 'yesterday' },
    { label: '7 Days', value: '7days' },
    { label: '30 Days', value: '30days' },
];

const periodLabel = computed(() => {
    return periodOptions.find((option) => option.value === props.filters.period)
        ?.label;
});
const requestLogChartUnit = computed(() => {
    return props.filters.period === 'today' ||
        props.filters.period === 'yesterday'
        ? 'per jam'
        : 'per hari';
});

const applyPeriod = (period: PeriodFilter): void => {
    router.get(
        dashboard.url({
            query: { period },
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const metrics = computed<DashboardMetric[]>(() => [
    {
        label: 'Total Website',
        value: props.dashboardData.totals.websites,
        tone: 'primary',
    },
    {
        label: 'RequestLog Today',
        value: props.dashboardData.totals.request_logs_today,
        tone: 'success',
    },
    {
        label: 'Total Post',
        value: props.dashboardData.totals.posts,
        tone: 'warning',
    },
    {
        label: `RequestLog ${periodLabel.value}`,
        value: props.dashboardData.totals.request_logs_period,
        tone: 'neutral',
    },
]);

const dailyChartData = computed(() => props.dashboardData.request_logs_daily);
const topRoutesChartData = computed(
    () => props.dashboardData.request_logs_top_routes,
);
const topCategoriesChartData = computed(() =>
    props.dashboardData.top_categories_by_posts.map((item, index, items) => ({
        ...item,
        rank: items.length - index,
    })),
);

const lineX = (d: DailyRequestLog): Date => {
    return new Date(d.date.includes('T') ? d.date : `${d.date}T00:00:00`);
};
const lineY = (d: DailyRequestLog): number => d.total;
const dailyTooltip = (d: DailyRequestLog): string => {
    return `<div class="space-y-1 rounded-lg border border-default bg-default px-3 py-2 shadow-lg">
        <p class="text-xs text-muted">${d.label}</p>
        <p class="text-sm font-semibold text-highlighted">${d.total.toLocaleString('id-ID')} requests</p>
    </div>`;
};

const lineTickFormat = (
    tick: number | Date,
    index: number,
    ticks: Array<number | Date>,
): string => {
    const date = tick instanceof Date ? tick : new Date(tick);

    if (
        props.filters.period === 'today' ||
        props.filters.period === 'yesterday'
    ) {
        return new Intl.DateTimeFormat('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    }

    if (index === 0 || index === ticks.length - 1) {
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
        }).format(date);
    }

    return '';
};

const lineValueFormat = (tick: number | Date): string => {
    if (tick instanceof Date) {
        return '';
    }

    return tick.toLocaleString('id-ID');
};

const donutValue = (d: TopRequestRoute): number => d.total;
const donutColor = (_d: TopRequestRoute, index: number): string => {
    return chartColors[index % chartColors.length];
};
const donutLegendColor = (index: number): string => {
    return chartColors[index % chartColors.length];
};
const donutTotal = computed(() => {
    return topRoutesChartData.value.reduce((sum, item) => sum + item.total, 0);
});
const donutCenterLabel = computed(() =>
    donutTotal.value.toLocaleString('id-ID'),
);
const donutCenterSubLabel = 'Requests';
const topCategoriesChartHeight = computed(() =>
    Math.max(topCategoriesChartData.value.length * 32, 320),
);

const topCategoryX = (d: { rank: number }): number => d.rank;
const topCategoryY = (d: { total: number }): number => d.total;

const topCategoryTicks = computed(() =>
    topCategoriesChartData.value.map((item) => item.rank),
);

const topCategoryTickFormat = (tick: number | Date): string => {
    if (tick instanceof Date) {
        return '';
    }

    const category = topCategoriesChartData.value.find(
        (item) => item.rank === tick,
    );

    return category?.name ?? '';
};

const topCategoryValueFormat = (tick: number | Date): string => {
    if (tick instanceof Date) {
        return '';
    }

    return tick.toLocaleString('id-ID');
};
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div
            class="flex flex-col gap-3 rounded-xl border border-default bg-default p-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">
                    Dashboard
                </h1>
                <p class="text-sm text-muted">
                    Filter request log: {{ periodLabel }}.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <UButton
                    v-for="option in periodOptions"
                    :key="option.value"
                    :label="option.label"
                    :color="
                        props.filters.period === option.value
                            ? 'primary'
                            : 'neutral'
                    "
                    :variant="
                        props.filters.period === option.value
                            ? 'solid'
                            : 'outline'
                    "
                    size="sm"
                    @click="applyPeriod(option.value)"
                />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <UCard
                v-for="metric in metrics"
                :key="metric.label"
                class="border border-default"
            >
                <div class="space-y-3">
                    <p class="text-sm text-muted">
                        {{ metric.label }}
                    </p>
                    <div class="flex items-end justify-between gap-3">
                        <p class="text-3xl font-semibold text-highlighted">
                            {{ metric.value.toLocaleString('id-ID') }}
                        </p>
                        <UBadge
                            :color="metric.tone"
                            variant="subtle"
                            label="Live"
                        />
                    </div>
                </div>
            </UCard>
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
            <UCard class="border border-default">
                <template #header>
                    <div>
                        <h2 class="text-lg font-semibold text-highlighted">
                            RequestLog Daily
                        </h2>
                        <p class="text-sm text-muted">
                            Aktivitas {{ requestLogChartUnit }} periode
                            {{ periodLabel }}.
                        </p>
                    </div>
                </template>

                <div class="space-y-4">
                    <div class="rounded-xl bg-muted/20 p-3">
                        <VisXYContainer :height="280" class="dashboard-chart">
                            <VisLine
                                :data="dailyChartData"
                                :x="lineX"
                                :y="lineY"
                                color="var(--color-chart-1)"
                                :lineWidth="3"
                            />
                            <VisScatter
                                :data="dailyChartData"
                                :x="lineX"
                                :y="lineY"
                                color="var(--color-chart-1)"
                                :size="8"
                                :strokeWidth="2"
                                strokeColor="var(--ui-bg)"
                                cursor="pointer"
                            />
                            <VisTooltip
                                :triggers="{
                                    [VisScatterSelectors.point]: dailyTooltip,
                                }"
                            />
                            <VisAxis
                                :data="dailyChartData"
                                type="x"
                                :numTicks="6"
                                :gridLine="false"
                                :domainLine="false"
                                :tickFormat="lineTickFormat"
                            />
                            <VisAxis
                                :data="dailyChartData"
                                type="y"
                                :numTicks="5"
                                :tickFormat="lineValueFormat"
                            />
                        </VisXYContainer>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-3">
                        <div
                            v-for="item in dailyChartData.slice(-3)"
                            :key="item.date"
                            class="rounded-lg border border-default bg-muted/20 px-3 py-2"
                        >
                            <p class="text-xs text-muted">
                                {{ item.label }}
                            </p>
                            <p class="text-lg font-semibold text-highlighted">
                                {{ item.total }}
                            </p>
                        </div>
                    </div>
                </div>
            </UCard>

            <UCard class="border border-default">
                <template #header>
                    <div>
                        <h2 class="text-lg font-semibold text-highlighted">
                            Top Request Routes
                        </h2>
                        <p class="text-sm text-muted">
                            Distribusi request terbanyak periode
                            {{ periodLabel }}.
                        </p>
                    </div>
                </template>

                <div class="space-y-5">
                    <div
                        v-if="topRoutesChartData.length > 0"
                        class="rounded-xl bg-muted/20 p-3"
                    >
                        <VisSingleContainer
                            :data="topRoutesChartData"
                            :height="260"
                        >
                            <VisDonut
                                :value="donutValue"
                                :color="donutColor"
                                :arcWidth="28"
                                :padAngle="0.03"
                                :cornerRadius="6"
                                :centralLabel="donutCenterLabel"
                                :centralSubLabel="donutCenterSubLabel"
                            />
                        </VisSingleContainer>
                    </div>

                    <div v-if="topRoutesChartData.length > 0" class="space-y-3">
                        <div
                            v-for="(item, index) in topRoutesChartData"
                            :key="item.route"
                            class="space-y-1"
                        >
                            <div
                                class="flex items-center justify-between gap-3 text-sm"
                            >
                                <div class="flex min-w-0 items-center gap-2">
                                    <span
                                        class="size-3 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                donutLegendColor(index),
                                        }"
                                    />
                                    <span class="truncate text-highlighted">
                                        {{ item.route }}
                                    </span>
                                </div>
                                <span class="whitespace-nowrap text-muted">
                                    {{ item.total }} •
                                    {{ item.percentage.toFixed(2) }}%
                                </span>
                            </div>
                            <div
                                class="h-2 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :style="{
                                        width: `${item.percentage}%`,
                                        backgroundColor:
                                            donutLegendColor(index),
                                    }"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="rounded-lg border border-dashed border-default px-4 py-6 text-center text-sm text-muted"
                    >
                        Belum ada request log untuk periode {{ periodLabel }}.
                    </div>
                </div>
            </UCard>
        </div>

        <UCard class="border border-default">
            <template #header>
                <div>
                    <h2 class="text-lg font-semibold text-highlighted">
                        Top Categories by Posts
                    </h2>
                    <p class="text-sm text-muted">
                        50 kategori dengan jumlah post terbanyak.
                    </p>
                </div>
            </template>

            <div class="space-y-4">
                <div
                    v-if="topCategoriesChartData.length > 0"
                    class="rounded-xl bg-muted/20 p-3"
                >
                    <VisXYContainer
                        :height="topCategoriesChartHeight"
                        :xDomain="[0, undefined]"
                        class="dashboard-chart"
                    >
                        <VisGroupedBar
                            :data="topCategoriesChartData"
                            :x="topCategoryX"
                            :y="[topCategoryY]"
                            orientation="horizontal"
                            color="var(--color-chart-2)"
                            :roundedCorners="6"
                            :barPadding="0.15"
                            :groupPadding="0.2"
                        />
                        <VisAxis
                            type="x"
                            :numTicks="6"
                            :tickFormat="topCategoryValueFormat"
                            :domainLine="false"
                        />
                        <VisAxis
                            type="y"
                            :tickValues="topCategoryTicks"
                            :tickFormat="topCategoryTickFormat"
                            :gridLine="false"
                            :domainLine="false"
                            :tickLine="false"
                            :tickTextWidth="220"
                        />
                    </VisXYContainer>
                </div>

                <div
                    v-if="topCategoriesChartData.length > 0"
                    class="grid gap-2 rounded-lg border border-default bg-muted/10 px-3 py-2 sm:grid-cols-3"
                >
                    <div
                        v-for="item in topCategoriesChartData.slice(0, 3)"
                        :key="`${item.name}-summary`"
                        class="rounded-lg bg-muted/20 px-3 py-2"
                    >
                        <p class="truncate text-xs text-muted">
                            {{ item.name }}
                        </p>
                        <p class="text-lg font-semibold text-highlighted">
                            {{ item.total.toLocaleString('id-ID') }}
                        </p>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-lg border border-dashed border-default px-4 py-6 text-center text-sm text-muted"
                >
                    Belum ada kategori yang terhubung ke post.
                </div>
            </div>
        </UCard>
    </div>
</template>
