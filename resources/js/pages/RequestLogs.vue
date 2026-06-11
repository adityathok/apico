<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { TableColumn } from '@nuxt/ui';
import { computed, ref, watch } from 'vue';
import { requestlogsIndex } from '@/routes';

type Website = {
    id: number;
    domain: string;
    status: string;
};

type License = {
    id: number;
    code: string;
    is_active: boolean;
};

type RequestLog = {
    id: number;
    route: string;
    method: string;
    request: Record<string, unknown> | unknown[] | null;
    status: number;
    website_id: number;
    license_id: number;
    created_at: string;
    updated_at: string;
    website?: Website | null;
    license?: License | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    path: string;
    per_page: number;
    to: number | null;
    total: number;
};

type RequestLogsResponse = {
    data: RequestLog[];
    meta: PaginationMeta;
};

const props = defineProps<{
    requestLogs: RequestLogsResponse;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Request Logs',
                href: requestlogsIndex(),
            },
        ],
    },
});

const columns: TableColumn<RequestLog>[] = [
    {
        accessorKey: 'route',
        header: 'Request',
    },
    {
        accessorKey: 'status',
        header: 'Status',
    },
    {
        accessorKey: 'website',
        header: 'Website',
    },
    {
        accessorKey: 'license',
        header: 'License',
    },
    {
        accessorKey: 'request',
        header: 'Payload',
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
    },
];

const search = ref('');
const currentPage = ref(props.requestLogs.meta.current_page);
const isLoading = ref(false);

const filteredRequestLogs = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return props.requestLogs.data;
    }

    return props.requestLogs.data.filter((requestLog) => {
        const searchableContent = [
            requestLog.route,
            requestLog.method,
            String(requestLog.status),
            requestLog.website?.domain,
            requestLog.website_id ? String(requestLog.website_id) : null,
            requestLog.license?.code,
            requestLog.license_id ? String(requestLog.license_id) : null,
            formatPayload(requestLog.request),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return searchableContent.includes(query);
    });
});

const paginationSummary = computed(() => {
    if (props.requestLogs.meta.total === 0) {
        return '0 request logs';
    }

    return `${props.requestLogs.meta.from}-${props.requestLogs.meta.to} of ${props.requestLogs.meta.total} request logs`;
});

const statusColor = (status: number): 'success' | 'warning' | 'error' => {
    if (status >= 500) {
        return 'error';
    }

    if (status >= 400) {
        return 'warning';
    }

    return 'success';
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

function formatPayload(payload: RequestLog['request']): string {
    if (!payload) {
        return '-';
    }

    return JSON.stringify(payload);
}

const visitPage = (page: number): void => {
    isLoading.value = true;

    router.get(
        requestlogsIndex.url({
            query: { page },
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
};

watch(
    () => props.requestLogs.meta.current_page,
    (page) => {
        currentPage.value = page;
    },
);

watch(currentPage, (page) => {
    if (page !== props.requestLogs.meta.current_page) {
        visitPage(page);
    }
});
</script>

<template>
    <Head title="Request Logs" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">
                    Request Logs
                </h1>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Search request logs..."
                    :disabled="isLoading"
                    class="w-full sm:w-72"
                />

                <UButton
                    icon="i-lucide-refresh-cw"
                    color="neutral"
                    variant="outline"
                    :loading="isLoading"
                    aria-label="Refresh request logs"
                    @click="visitPage(currentPage)"
                />
            </div>
        </div>

        <div
            class="overflow-hidden rounded-lg border border-default bg-default"
        >
            <UTable
                :data="filteredRequestLogs"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <template #route-cell="{ row }">
                    <div class="flex items-center gap-3">
                        <UAvatar
                            :alt="row.original.route"
                            icon="i-lucide-activity"
                            size="lg"
                        />

                        <div class="min-w-0">
                            <p class="truncate font-medium text-highlighted">
                                {{ row.original.route }}
                            </p>
                            <p class="text-xs text-muted">
                                {{ row.original.method }} · ID
                                {{ row.original.id }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #status-cell="{ row }">
                    <UBadge
                        :color="statusColor(row.original.status)"
                        variant="subtle"
                        :label="String(row.original.status)"
                    />
                </template>

                <template #website-cell="{ row }">
                    <div v-if="row.original.website" class="min-w-0">
                        <p
                            class="truncate text-sm font-medium text-highlighted"
                        >
                            {{ row.original.website.domain }}
                        </p>
                        <p class="text-xs text-muted">
                            ID {{ row.original.website.id }}
                        </p>
                    </div>
                    <span v-else class="text-sm text-muted">
                        ID {{ row.original.website_id }}
                    </span>
                </template>

                <template #license-cell="{ row }">
                    <div v-if="row.original.license" class="min-w-0">
                        <UBadge
                            color="neutral"
                            variant="subtle"
                            :label="row.original.license.code"
                        />
                        <p class="mt-1 text-xs text-muted">
                            ID {{ row.original.license.id }}
                        </p>
                    </div>
                    <span v-else class="text-sm text-muted">
                        ID {{ row.original.license_id }}
                    </span>
                </template>

                <template #request-cell="{ row }">
                    <p class="max-w-md truncate font-mono text-xs text-muted">
                        {{ formatPayload(row.original.request) }}
                    </p>
                </template>

                <template #created_at-cell="{ row }">
                    {{ formatDateTime(row.original.created_at) }}
                </template>

                <template #empty>
                    <div class="flex flex-col items-center gap-2 py-10">
                        <UIcon
                            name="i-lucide-inbox"
                            class="size-8 text-muted"
                        />
                        <p class="font-medium text-highlighted">
                            Tidak ada request log
                        </p>
                        <p class="text-sm text-muted">
                            Data belum tersedia atau tidak cocok dengan
                            pencarian.
                        </p>
                    </div>
                </template>
            </UTable>

            <div
                v-if="
                    requestLogs.meta &&
                    requestLogs.meta.total > requestLogs.meta.per_page
                "
                class="flex flex-col gap-3 border-t border-default px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>

                <UPagination
                    v-model:page="currentPage"
                    :total="requestLogs.meta.total"
                    :items-per-page="requestLogs.meta.per_page"
                    :disabled="isLoading"
                />
            </div>
        </div>
    </div>
</template>
