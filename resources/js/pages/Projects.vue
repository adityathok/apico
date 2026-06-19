<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { FormError, FormSubmitEvent, TableColumn } from '@nuxt/ui';
import axios, { AxiosError } from 'axios';
import { computed, reactive, ref, watch } from 'vue';
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

type ResourceResponse<T> = {
    data: T;
};

type ProjectFormState = {
    name: string;
    version: string;
    github_url: string;
    description: string;
    type: ProjectType;
    parent_id: string;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
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
const isSaving = ref(false);
const editingProjectId = ref<number | null>(null);
const formMessage = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});
const packageFile = ref<File | null>(null);
const packageFileInput = ref<HTMLInputElement | null>(null);

const state = reactive<ProjectFormState>({
    name: '',
    version: '',
    github_url: '',
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
        : 'Required. Upload file ZIP package project.',
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

const projectTypeOptions = [
    { label: 'Internal Project', value: 'project_internal' },
    { label: 'Client Project', value: 'project_client' },
    { label: 'WordPress Theme', value: 'wp_theme' },
    { label: 'WordPress Plugin', value: 'wp_plugin' },
    { label: 'WordPress Child Theme', value: 'wp_theme_child' },
];

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

const fieldError = (name: string): string | undefined => {
    return serverErrors.value[name];
};

const validate = (formState: Partial<ProjectFormState>): FormError[] => {
    const errors: FormError[] = [];

    if (!formState.name?.trim()) {
        errors.push({ name: 'name', message: 'Nama project wajib diisi.' });
    }

    if (!formState.type) {
        errors.push({ name: 'type', message: 'Type project wajib dipilih.' });
    }

    if (!isEditing.value && !packageFile.value) {
        errors.push({
            name: 'package_file',
            message: 'File ZIP package wajib diupload.',
        });
    }

    return errors;
};

const resetForm = (): void => {
    state.name = '';
    state.version = '';
    state.github_url = '';
    state.description = '';
    state.type = 'project_internal';
    state.parent_id = noParentValue;
    packageFile.value = null;
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
    state.version = project.version ?? '';
    state.github_url = project.github_url ?? '';
    state.description = project.description ?? '';
    state.type = project.type;
    state.parent_id = project.parent_id
        ? String(project.parent_id)
        : noParentValue;
    packageFile.value = null;
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

const nullableTrimmed = (value: string): string | null => {
    const trimmedValue = value.trim();

    return trimmedValue === '' ? null : trimmedValue;
};

const buildPayload = (): FormData => {
    const payload = new FormData();

    payload.append('name', state.name.trim());
    payload.append('type', state.type);

    const version = nullableTrimmed(state.version);
    const githubUrl = nullableTrimmed(state.github_url);
    const description = nullableTrimmed(state.description);
    const parentId =
        state.parent_id === noParentValue ? null : Number(state.parent_id);

    if (version !== null) {
        payload.append('version', version);
    }

    if (githubUrl !== null) {
        payload.append('github_url', githubUrl);
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

    return payload;
};

const refreshProjects = (): void => {
    visitPage(currentPage.value);
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
</script>

<template>
    <Head title="Projects" />

    <div>
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

                    <template #actions-cell="{ row }">
                        <div class="flex justify-end gap-1">
                            <UButton
                                icon="i-lucide-pencil"
                                color="neutral"
                                variant="ghost"
                                aria-label="Edit project"
                                :disabled="isLoading || isSaving"
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
                        <p
                            v-else-if="isEditing && currentPackageFileUrl"
                            class="mt-2 text-xs text-muted"
                        >
                            File saat ini:
                            <a
                                :href="currentPackageFileUrl"
                                target="_blank"
                                class="underline"
                            >
                                Download current package
                            </a>
                        </p>
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
    </div>
</template>
