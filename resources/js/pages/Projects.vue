<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { FormError, FormSubmitEvent, TableColumn } from '@nuxt/ui';
import axios, { AxiosError } from 'axios';
import { computed, reactive, ref, watch } from 'vue';
import ProjectChangelogs from '@/components/ProjectChangelogs.vue';
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

type ParentProjectOption = {
    id: number;
    name: string;
};

type Project = {
    id: number;
    name: string;
    slug: string;
    version: string | null;
    requires: string | null;
    requires_php: string | null;
    plugin_wp_required: boolean | null;
    github_url: string | null;
    package_file: string | null;
    package_file_url: string | null;
    package_external_url: string | null;
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

type ResourceResponse<T> = {
    data: T;
};

type ProjectFormState = {
    name: string;
    slug: string;
    version: string;
    requires_wp: string;
    requires_php: string;
    plugin_wp_required: boolean;
    github_url: string;
    package_external_url: string;
    icon: string;
    screenshot: string;
    description: string;
    type: ProjectType;
    parent_id: string;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

type MessageResponse = {
    message: string;
};

const noParentValue = '__none__';

const props = defineProps<{
    projects: ProjectsResponse;
    parentProjects: ParentProjectOption[];
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
        accessorKey: 'updated_at',
        header: 'Last Updated',
    },
    {
        id: 'actions',
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
const isModalOpen = ref(false);
const isChangelogModalOpen = ref(false);
const isSaving = ref(false);
const syncingProjectId = ref<number | null>(null);
const editingProjectId = ref<number | null>(null);
const selectedChangelogProject = ref<Project | null>(null);
const formMessage = ref<string | null>(null);
const syncMessage = ref<string | null>(null);
const syncMessageType = ref<'success' | 'error'>('success');
const serverErrors = ref<Record<string, string>>({});
const packageFile = ref<File | null>(null);
const packageFileInput = ref<HTMLInputElement | null>(null);
const removePackageFile = ref(false);

const state = reactive<ProjectFormState>({
    name: '',
    slug: '',
    version: '',
    requires_wp: '',
    requires_php: '',
    plugin_wp_required: false,
    github_url: '',
    package_external_url: '',
    description: '',
    type: 'project_internal',
    parent_id: noParentValue,
});

const filteredProjects = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return props.projects.data;
    }

    return props.projects.data.filter((project) => {
        const searchableContent = [
            project.name,
            project.slug,
            project.version,
            project.requires,
            project.requires_php,
            String(project.plugin_wp_required),
            project.github_url,
            project.package_file,
            project.package_file_url,
            project.package_external_url,
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

const isEditing = computed(() => editingProjectId.value !== null);
const modalTitle = computed(() =>
    isEditing.value ? 'Edit Project' : 'Add Project',
);
const modalDescription = computed(() =>
    isEditing.value
        ? 'Perbarui detail project yang sudah ada.'
        : 'Tambahkan project baru ke daftar admin.',
);
const submitLabel = computed(() =>
    isEditing.value ? 'Update Project' : 'Create Project',
);
const packageFileLabel = computed(() =>
    isEditing.value ? 'Replace ZIP Package' : 'ZIP Package',
);
const packageFileHint = computed(() =>
    isEditing.value
        ? 'Optional. Upload file baru hanya jika ingin mengganti package lama.'
        : 'Optional jika Package External URL diisi. Upload file ZIP jika package disimpan lokal.',
);
const currentPackageFileUrl = computed(() => {
    if (editingProjectId.value === null) {
        return null;
    }

    return (
        props.projects.data.find(
            (project) => project.id === editingProjectId.value,
        )?.package_file_url ?? null
    );
});
const showsCurrentPackageFile = computed(() => {
    return isEditing.value && currentPackageFileUrl.value !== null && !removePackageFile.value;
});

const projectTypeOptions = [
    { label: 'Internal Project', value: 'project_internal' },
    { label: 'Client Project', value: 'project_client' },
    { label: 'WordPress Theme', value: 'wp_theme' },
    { label: 'WordPress Plugin', value: 'wp_plugin' },
    { label: 'WordPress Child Theme', value: 'wp_theme_child' },
];

const wordPressProjectTypes: ProjectType[] = [
    'wp_theme',
    'wp_plugin',
    'wp_theme_child',
];

const showsRequiredVersions = computed(() => {
    return wordPressProjectTypes.includes(state.type);
});

const showsPluginWpRequired = computed(() => {
    return state.type === 'wp_plugin';
});

const parentOptions = computed(() => {
    return [
        {
            label: 'No parent project',
            value: noParentValue,
        },
        ...props.parentProjects
            .filter((project) => project.id !== editingProjectId.value)
            .map((project) => ({
                label: project.name,
                value: String(project.id),
            })),
    ];
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

const slugify = (value: string): string => {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
};

const fieldError = (name: string): string | undefined => {
    return serverErrors.value[name];
};

const validate = (formState: Partial<ProjectFormState>): FormError[] => {
    const errors: FormError[] = [];

    if (!formState.name?.trim()) {
        errors.push({ name: 'name', message: 'Nama project wajib diisi.' });
    }

    if (!formState.slug?.trim()) {
        errors.push({ name: 'slug', message: 'Slug project wajib diisi.' });
    }

    if (!formState.type) {
        errors.push({ name: 'type', message: 'Type project wajib dipilih.' });
    }

    if (
        !isEditing.value &&
        !packageFile.value &&
        !formState.package_external_url?.trim()
    ) {
        errors.push({
            name: 'package_file',
            message: 'Upload file ZIP atau isi Package External URL.',
        });
    }

    return errors;
};

const resetForm = (): void => {
    state.name = '';
    state.slug = '';
    state.version = '';
    state.requires_wp = '';
    state.requires_php = '';
    state.plugin_wp_required = false;
    state.github_url = '';
    state.package_external_url = '';
    state.description = '';
    state.type = 'project_internal';
    state.parent_id = noParentValue;
    packageFile.value = null;
    removePackageFile.value = false;
    if (packageFileInput.value) {
        packageFileInput.value.value = '';
    }
    editingProjectId.value = null;
    formMessage.value = null;
    serverErrors.value = {};
};

const openCreateModal = (): void => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (project: Project): void => {
    state.name = project.name;
    state.slug = project.slug;
    state.version = project.version ?? '';
    state.requires_wp = project.requires ?? '';
    state.requires_php = project.requires_php ?? '';
    state.plugin_wp_required = Boolean(project.plugin_wp_required);
    state.github_url = project.github_url ?? '';
    state.package_external_url = project.package_external_url ?? '';
    state.description = project.description ?? '';
    state.type = project.type;
    state.parent_id = project.parent_id
        ? String(project.parent_id)
        : noParentValue;
    packageFile.value = null;
    removePackageFile.value = false;
    if (packageFileInput.value) {
        packageFileInput.value.value = '';
    }
    editingProjectId.value = project.id;
    formMessage.value = null;
    serverErrors.value = {};
    isModalOpen.value = true;
};

const closeModal = (): void => {
    if (isSaving.value) {
        return;
    }

    isModalOpen.value = false;
    resetForm();
};

const openChangelogModal = (project: Project): void => {
    selectedChangelogProject.value = project;
    isChangelogModalOpen.value = true;
};

const closeChangelogModal = (): void => {
    isChangelogModalOpen.value = false;
    selectedChangelogProject.value = null;
};

const nullableTrimmed = (value: string): string | null => {
    const trimmedValue = value.trim();

    return trimmedValue === '' ? null : trimmedValue;
};

const buildPayload = (): FormData => {
    const payload = new FormData();

    payload.append('name', state.name.trim());
    payload.append('slug', state.slug.trim());
    payload.append('type', state.type);

    const version = nullableTrimmed(state.version);
    const requiresWp = nullableTrimmed(state.requires_wp);
    const requiresPhp = nullableTrimmed(state.requires_php);
    const githubUrl = nullableTrimmed(state.github_url);
    const packageExternalUrl = nullableTrimmed(state.package_external_url);
    const description = nullableTrimmed(state.description);
    const parentId =
        state.parent_id === noParentValue ? null : Number(state.parent_id);

    if (version !== null) {
        payload.append('version', version);
    }

    if (showsRequiredVersions.value && requiresWp !== null) {
        payload.append('requires_wp', requiresWp);
    }

    if (showsRequiredVersions.value && requiresPhp !== null) {
        payload.append('requires_php', requiresPhp);
    }

    if (showsPluginWpRequired.value) {
        payload.append('plugin_wp_required', state.plugin_wp_required ? '1' : '0');
    }

    if (githubUrl !== null) {
        payload.append('github_url', githubUrl);
    }

    if (packageExternalUrl !== null) {
        payload.append('package_external_url', packageExternalUrl);
    }

    if (icon !== null) {
        payload.append('icon', icon);
    }

    if (screenshot !== null) {
        payload.append('screenshot', screenshot);
    }

    if (description !== null) {
        payload.append('description', description);
    }

    if (parentId !== null) {
        payload.append('parent_id', String(parentId));
    }

    if (packageFile.value) {
        payload.append('package_file', packageFile.value);
    }

    if (removePackageFile.value) {
        payload.append('remove_package_file', '1');
    }

    return payload;
};

const refreshProjects = (): void => {
    visitPage(currentPage.value);
};

const showSyncMessage = (
    message: string,
    type: 'success' | 'error',
): void => {
    syncMessage.value = message;
    syncMessageType.value = type;
};

const storeProject = async (): Promise<Project> => {
    const response = await axios.post<ResourceResponse<Project>>(
        '/ajax/projects',
        buildPayload(),
    );

    return response.data.data;
};

const updateProject = async (id: number): Promise<Project> => {
    const payload = buildPayload();
    payload.append('_method', 'PATCH');

    const response = await axios.post<ResourceResponse<Project>>(
        `/ajax/projects/${id}`,
        payload,
    );

    return response.data.data;
};

const handleValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        formMessage.value = 'Project gagal disimpan.';

        return;
    }

    const response = error.response.data as ValidationResponse;
    const errors = response.errors ?? {};

    serverErrors.value = Object.fromEntries(
        Object.entries(errors).map(([name, messages]) => [
            name,
            messages[0] ?? 'Invalid value.',
        ]),
    );
};

const updatePackageFile = (event: Event): void => {
    const target = event.target as HTMLInputElement;
    packageFile.value = target.files?.[0] ?? null;
    if (packageFile.value) {
        removePackageFile.value = false;
    }
};

const markCurrentPackageForRemoval = (): void => {
    removePackageFile.value = true;
    packageFile.value = null;

    if (packageFileInput.value) {
        packageFileInput.value.value = '';
    }
};

const keepCurrentPackageFile = (): void => {
    removePackageFile.value = false;
};

const syncGithubRelease = async (project: Project): Promise<void> => {
    syncingProjectId.value = project.id;
    syncMessage.value = null;

    try {
        const response = await axios.post<MessageResponse>(
            `/ajax/projects/${project.id}/sync-github-release`,
        );

        showSyncMessage(response.data.message, 'success');
        refreshProjects();
    } catch (error) {
        if (error instanceof AxiosError) {
            const message =
                (error.response?.data as Partial<MessageResponse> | undefined)
                    ?.message ?? 'Project gagal sync release GitHub.';

            showSyncMessage(message, 'error');
        } else {
            showSyncMessage('Project gagal sync release GitHub.', 'error');
        }
    } finally {
        syncingProjectId.value = null;
    }
};

const submitProject = async (
    _event: FormSubmitEvent<ProjectFormState>,
): Promise<void> => {
    isSaving.value = true;
    formMessage.value = null;
    serverErrors.value = {};

    try {
        if (editingProjectId.value) {
            await updateProject(editingProjectId.value);
        } else {
            await storeProject();
        }

        isModalOpen.value = false;
        resetForm();
        refreshProjects();
    } catch (error) {
        handleValidationErrors(error);
    } finally {
        isSaving.value = false;
    }
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
    () => state.name,
    (nameValue) => {
        if (!isEditing.value && state.slug === '') {
            state.slug = slugify(nameValue);
        }
    },
);

watch(
    () => state.type,
    (typeValue) => {
        if (!wordPressProjectTypes.includes(typeValue)) {
            state.requires_wp = '';
            state.requires_php = '';
        }

        if (typeValue !== 'wp_plugin') {
            state.plugin_wp_required = false;
        }
    },
);

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

watch(isModalOpen, (open) => {
    if (!open && !isSaving.value) {
        resetForm();
    }
});

watch(isChangelogModalOpen, (open) => {
    if (!open) {
        selectedChangelogProject.value = null;
    }
});
</script>

<template>
    <Head title="Projects" />

    <div>
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <UAlert
                v-if="syncMessage"
                :color="syncMessageType === 'success' ? 'success' : 'error'"
                variant="soft"
                :icon="
                    syncMessageType === 'success'
                        ? 'i-lucide-circle-check'
                        : 'i-lucide-circle-alert'
                "
                :title="
                    syncMessageType === 'success'
                        ? 'GitHub release tersinkron'
                        : 'Sync GitHub release gagal'
                "
                :description="syncMessage"
            />

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
                        icon="i-lucide-plus"
                        label="Add"
                        :disabled="isLoading"
                        @click="openCreateModal"
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
                            <UBadge
                                color="neutral"
                                variant="subtle"
                                :label="row.original.slug"
                            />
                        </div>
                    </template>

                    <template #slug-cell="{ row }">
                        <UBadge
                            color="neutral"
                            variant="subtle"
                            :label="row.original.slug"
                        />
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
                            class="text-sm flex items-center gap-1"
                        >
                            <UIcon name="i-lucide-github" />
                            Repository
                        </ULink>
                        <span v-else class="text-sm text-muted">-</span>
                    </template>

                    <template #package_file_url-cell="{ row }">                       
                        <UButton
                            v-if="row.original.package_file_url"
                            type="button"
                            label="Download"
                            icon="i-lucide-download"
                            color="primary"
                            variant="soft"
                            :to="row.original.package_file_url"
                            target="_blank"
                            size="sm"
                        />
                        <span v-else class="text-sm text-muted">-</span>
                    </template>

                    <template #updated_at-cell="{ row }">
                        {{ formatDate(row.original.updated_at) }}
                    </template>

                    <template #actions-cell="{ row }">
                        <div class="flex justify-end gap-1">
                            <UButton
                                icon="i-lucide-cloud-download"
                                color="success"
                                variant="ghost"
                                aria-label="Sync GitHub release"
                                :disabled="
                                    isLoading ||
                                    isSaving ||
                                    !row.original.github_url ||
                                    syncingProjectId !== null
                                "
                                :loading="syncingProjectId === row.original.id"
                                @click="syncGithubRelease(row.original)"
                            />
                            <UButton
                                icon="i-lucide-scroll-text"
                                color="primary"
                                variant="ghost"
                                aria-label="Manage project changelogs"
                                :disabled="isLoading || isSaving || syncingProjectId !== null"
                                @click="openChangelogModal(row.original)"
                            />
                            <UButton
                                icon="i-lucide-pencil"
                                color="neutral"
                                variant="ghost"
                                aria-label="Edit project"
                                :disabled="isLoading || isSaving || syncingProjectId !== null"
                                @click="openEditModal(row.original)"
                            />
                        </div>
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

        <UModal
            v-model:open="isModalOpen"
            :title="modalTitle"
            :description="modalDescription"
            :ui="{ footer: 'justify-end' }"
        >
            <template #body>
                <UAlert
                    v-if="formMessage"
                    color="error"
                    variant="soft"
                    icon="i-lucide-circle-alert"
                    title="Ada masalah"
                    :description="formMessage"
                    class="mb-4"
                />

                <UForm
                    id="project-form"
                    :state="state"
                    :validate="validate"
                    class="space-y-4"
                    @submit="submitProject"
                >
                    <UFormField
                        name="name"
                        label="Name"
                        required
                        :error="fieldError('name')"
                    >
                        <UInput
                            v-model="state.name"
                            placeholder="Velocity Addons"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="slug"
                        label="Slug"
                        required
                        :error="fieldError('slug')"
                    >
                        <UInput
                            v-model="state.slug"
                            placeholder="velocity-addons"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <UFormField
                            name="type"
                            label="Type"
                            required
                            :error="fieldError('type')"
                        >
                            <USelect
                                v-model="state.type"
                                :items="projectTypeOptions"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            name="parent_id"
                            label="Parent Project"
                            hint="Optional"
                            :error="fieldError('parent_id')"
                        >
                            <USelect
                                v-model="state.parent_id"
                                :items="parentOptions"
                                placeholder="No parent project"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <UFormField
                            name="version"
                            label="Version"
                            hint="Optional"
                            :error="fieldError('version')"
                        >
                            <UInput
                                v-model="state.version"
                                placeholder="1.0.0"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            name="github_url"
                            label="GitHub URL"
                            hint="Optional"
                            :error="fieldError('github_url')"
                        >
                            <UInput
                                v-model="state.github_url"
                                placeholder="https://github.com/example/repo"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>
                    </div>

                    <div
                        v-if="showsRequiredVersions"
                        class="space-y-4 rounded-xl border border-default bg-muted/30 p-4"
                    >

                        <div class="flex gap-2 items-center">
                            <div class="text-blue-700">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-wordpress" viewBox="0 0 16 16">
                                    <path d="M12.633 7.653c0-.848-.305-1.435-.566-1.892l-.08-.13c-.317-.51-.594-.958-.594-1.48 0-.63.478-1.218 1.152-1.218q.03 0 .058.003l.031.003A6.84 6.84 0 0 0 8 1.137 6.86 6.86 0 0 0 2.266 4.23c.16.005.313.009.442.009.717 0 1.828-.087 1.828-.087.37-.022.414.521.044.565 0 0-.371.044-.785.065l2.5 7.434 1.5-4.506-1.07-2.929c-.369-.022-.719-.065-.719-.065-.37-.022-.326-.588.043-.566 0 0 1.134.087 1.808.087.718 0 1.83-.087 1.83-.087.37-.022.413.522.043.566 0 0-.372.043-.785.065l2.48 7.377.684-2.287.054-.173c.27-.86.469-1.495.469-2.046zM1.137 8a6.86 6.86 0 0 0 3.868 6.176L1.73 5.206A6.8 6.8 0 0 0 1.137 8"/>
                                    <path d="M6.061 14.583 8.121 8.6l2.109 5.78q.02.05.049.094a6.85 6.85 0 0 1-4.218.109m7.96-9.876q.046.328.047.706c0 .696-.13 1.479-.522 2.458l-2.096 6.06a6.86 6.86 0 0 0 2.572-9.224z"/>
                                    <path fill-rule="evenodd" d="M0 8c0-4.411 3.589-8 8-8s8 3.589 8 8-3.59 8-8 8-8-3.589-8-8m.367 0c0 4.209 3.424 7.633 7.633 7.633S15.632 12.209 15.632 8C15.632 3.79 12.208.367 8 .367 3.79.367.367 3.79.367 8"/>
                                </svg>
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm font-medium text-default">
                                    WordPress Compatibility
                                </p>
                                <p class="text-xs text-muted">
                                    Konfigurasi kebutuhan minimum WordPress untuk project ini.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <UFormField
                                name="requires_wp"
                                label="Requires WP"
                                hint="Optional"
                                :error="fieldError('requires_wp')"
                            >
                                <UInput
                                    v-model="state.requires_wp"
                                    placeholder="6.7"
                                    :disabled="isSaving"
                                    class="w-full"
                                />
                            </UFormField>

                            <UFormField
                                name="requires_php"
                                label="Requires PHP"
                                hint="Optional"
                                :error="fieldError('requires_php')"
                            >
                                <UInput
                                    v-model="state.requires_php"
                                    placeholder="8.2"
                                    :disabled="isSaving"
                                    class="w-full"
                                />
                            </UFormField>
                        </div>

                        <UFormField
                            v-if="showsPluginWpRequired"
                            name="plugin_wp_required"
                            label="Plugin WP Required"
                            hint="Optional"
                            :error="fieldError('plugin_wp_required')"
                        >
                            <div class="flex items-center gap-3 rounded-lg border border-default bg-default px-3 py-2">
                                <UCheckbox
                                    v-model="state.plugin_wp_required"
                                    :disabled="isSaving"
                                />
                                <span class="text-sm text-highlighted">
                                    Wajib plugin WordPress
                                </span>
                            </div>
                        </UFormField>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <UFormField
                            name="package_external_url"
                            label="Package External URL"
                            hint="Optional"
                            :error="fieldError('package_external_url')"
                        >
                            <UInput
                                v-model="state.package_external_url"
                                placeholder="https://example.com/downloads/package.zip"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            name="icon"
                            label="Icon"
                            hint="Optional"
                            :error="fieldError('icon')"
                        >
                            <UInput
                                v-model="state.icon"
                                placeholder="uploads/projects/icon.png"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>
                    </div>

                    <UFormField
                        name="screenshot"
                        label="Screenshot"
                        hint="Optional"
                        :error="fieldError('screenshot')"
                    >
                        <UInput
                            v-model="state.screenshot"
                            placeholder="uploads/projects/screenshot.png"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="package_file"
                        :label="packageFileLabel"
                        :hint="packageFileHint"
                        :error="fieldError('package_file')"
                    >
                        <input
                            ref="packageFileInput"
                            type="file"
                            accept=".zip,application/zip"
                            :disabled="isSaving"
                            class="block w-full rounded-md border border-default bg-default px-3 py-2 text-sm"
                            @change="updatePackageFile"
                        >
                        <p
                            v-if="packageFile"
                            class="mt-2 text-xs text-muted"
                        >
                            File dipilih: {{ packageFile.name }}
                        </p>


                        <div
                            v-if="showsCurrentPackageFile"
                            class="mt-3 flex items-center gap-2"
                        >                            
                            <UButton
                                type="button"
                                label="Download current package"
                                icon="i-lucide-download"
                                color="success"
                                variant="soft"
                                :to="currentPackageFileUrl"
                                target="_blank"
                            />
                            <UButton
                                type="button"
                                label="Hapus file ZIP"
                                icon="i-lucide-trash"
                                color="error"
                                variant="soft"
                                :disabled="isSaving"
                                @click="markCurrentPackageForRemoval"
                            />
                        </div>
                        
                        <div
                            v-else-if="isEditing && removePackageFile"
                            class="mt-3 flex flex-wrap items-center gap-2 rounded-lg border border-error/30 bg-error/10 px-3 py-2"
                        >
                            <span class="text-xs text-error">
                                File ZIP saat ini akan dihapus ketika project disimpan.
                            </span>
                            <UButton
                                type="button"
                                label="Batal hapus"
                                color="neutral"
                                variant="ghost"
                                size="xs"
                                :disabled="isSaving"
                                @click="keepCurrentPackageFile"
                            />
                        </div>
                    </UFormField>

                    <UFormField
                        name="description"
                        label="Description"
                        hint="Optional"
                        :error="fieldError('description')"
                    >
                        <UTextarea
                            v-model="state.description"
                            :rows="4"
                            placeholder="Jelaskan project ini secara singkat."
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>
                </UForm>
            </template>

            <template #footer>
                <UButton
                    label="Cancel"
                    color="neutral"
                    variant="outline"
                    :disabled="isSaving"
                    @click="closeModal"
                />

                <UButton
                    type="submit"
                    form="project-form"
                    icon="i-lucide-save"
                    :label="submitLabel"
                    :loading="isSaving"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isChangelogModalOpen"
            :title="
                selectedChangelogProject
                    ? `Changelogs: ${selectedChangelogProject.name}`
                    : 'Project Changelogs'
            "
            :description="
                selectedChangelogProject
                    ? `Kelola changelog untuk project ${selectedChangelogProject.slug}.`
                    : 'Kelola changelog project.'
            "
            :ui="{
                content: 'sm:max-w-5xl',
            }"
        >
            <template #body>
                <ProjectChangelogs
                    v-if="selectedChangelogProject"
                    :id_project="selectedChangelogProject.id"
                />
            </template>

            <template #footer>
                <UButton
                    label="Tutup"
                    color="neutral"
                    variant="outline"
                    @click="closeChangelogModal"
                />
            </template>
        </UModal>
    </div>
</template>
