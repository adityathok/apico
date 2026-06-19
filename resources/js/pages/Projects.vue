<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { TableColumn } from '@nuxt/ui';
import { computed, ref, watch } from 'vue';
import { projects as projectsPage } from '@/routes';

type ProjectType =
    | 'project_internal'
    | 'project_client'
    | 'wp_theme'
    | 'wp_plugin'
    | 'wp_theme_child';

type ProjectParent = {
    id: number;
    name: string;
};

type Project = {
    id: number;
    name: string;
    version: string | null;
    github_url: string | null;
    package_file_url: string | null;
    description: string | null;
    type: ProjectType;
    parent_id: number | null;
    created_at: string;
    updated_at: string;
    parent?: ProjectParent | null;
};

type PaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
};

type ProjectsResponse = {
    data: Project[];
    meta: PaginationMeta;
};

const props = defineProps<{
    projects: ProjectsResponse;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Projects',
                href: projectsPage(),
            },
        ],
    },
});

const columns: TableColumn<Project>[] = [
    {
        accessorKey: 'name',
        header: 'Project',
    },
    {
        accessorKey: 'type',
        header: 'Type',
    },
    {
        accessorKey: 'parent',
        header: 'Parent',
    },
    {
        accessorKey: 'version',
        header: 'Version',
    },
    {
        accessorKey: 'github_url',
        header: 'GitHub',
    },
    {
        accessorKey: 'package_file_url',
        header: 'Package',
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
    },
];

const projectTypeLabels: Record<ProjectType, string> = {
    project_internal: 'Internal',
    project_client: 'Client',
    wp_theme: 'WP Theme',
    wp_plugin: 'WP Plugin',
    wp_theme_child: 'WP Child Theme',
};

const projectTypeLabel = (type: ProjectType): string => {
    return projectTypeLabels[type];
};

const search = ref('');
const currentPage = ref(props.projects.meta.current_page);
const isLoading = ref(false);

const filteredProjects = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return props.projects.data;
    }

    return props.projects.data.filter((project) => {
        const searchableContent = [
            project.name,
            project.version,
            project.github_url,
            project.package_file_url,
            project.description,
            project.type,
            projectTypeLabel(project.type),
            project.parent?.name,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return searchableContent.includes(query);
    });
});

const paginationSummary = computed(() => {
    if (props.projects.meta.total === 0) {
        return '0 projects';
    }

    return `${props.projects.meta.from}-${props.projects.meta.to} of ${props.projects.meta.total} projects`;
});

const typeColor = (
    type: ProjectType,
): 'primary' | 'secondary' | 'success' | 'warning' | 'neutral' => {
    if (type === 'project_internal') {
        return 'primary';
    }

    if (type === 'project_client') {
        return 'success';
    }

    if (type === 'wp_plugin') {
        return 'warning';
    }

    return 'neutral';
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
    }).format(new Date(value));
};

const visitPage = (page: number): void => {
    isLoading.value = true;

    router.get(
        projectsPage.url({
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
    () => props.projects.meta.current_page,
    (page) => {
        currentPage.value = page;
    },
);

watch(currentPage, (page) => {
    if (page !== props.projects.meta.current_page) {
        visitPage(page);
    }
});
</script>

<template>
    <Head title="Projects" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">
                    Projects
                </h1>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Search projects..."
                    :disabled="isLoading"
                    class="w-full sm:w-72"
                />

                <UButton
                    icon="i-lucide-refresh-cw"
                    color="neutral"
                    variant="outline"
                    :loading="isLoading"
                    aria-label="Refresh projects"
                    @click="visitPage(currentPage)"
                />
            </div>
        </div>

        <div
            class="overflow-hidden rounded-lg border border-default bg-default"
        >
            <UTable
                :data="filteredProjects"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <template #name-cell="{ row }">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-highlighted">
                            {{ row.original.name }}
                        </p>
                        <p class="truncate text-xs text-muted">
                            {{ row.original.description || 'No description' }}
                        </p>
                    </div>
                </template>

                <template #type-cell="{ row }">
                    <UBadge
                        :color="typeColor(row.original.type)"
                        variant="subtle"
                        :label="projectTypeLabel(row.original.type)"
                    />
                </template>

                <template #parent-cell="{ row }">
                    <span class="text-sm text-muted">
                        {{ row.original.parent?.name || '-' }}
                    </span>
                </template>

                <template #version-cell="{ row }">
                    <UBadge
                        color="neutral"
                        variant="subtle"
                        :label="row.original.version || '-'"
                    />
                </template>

                <template #github_url-cell="{ row }">
                    <ULink
                        v-if="row.original.github_url"
                        :to="row.original.github_url"
                        target="_blank"
                        class="text-sm"
                    >
                        Repository
                    </ULink>
                    <span v-else class="text-sm text-muted">-</span>
                </template>

                <template #package_file_url-cell="{ row }">
                    <ULink
                        v-if="row.original.package_file_url"
                        :to="row.original.package_file_url"
                        target="_blank"
                        class="text-sm"
                    >
                        Download
                    </ULink>
                    <span v-else class="text-sm text-muted">-</span>
                </template>

                <template #created_at-cell="{ row }">
                    {{ formatDate(row.original.created_at) }}
                </template>

                <template #empty>
                    <div class="flex flex-col items-center gap-2 py-10">
                        <UIcon
                            name="i-lucide-inbox"
                            class="size-8 text-muted"
                        />
                        <p class="font-medium text-highlighted">
                            Tidak ada project
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
                    props.projects.meta &&
                    props.projects.meta.total > props.projects.meta.per_page
                "
                class="flex flex-col gap-3 border-t border-default px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>

                <UPagination
                    v-model:page="currentPage"
                    :total="props.projects.meta.total"
                    :items-per-page="props.projects.meta.per_page"
                    :disabled="isLoading"
                />
            </div>
        </div>
    </div>
</template>
