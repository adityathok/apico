<script setup lang="ts">
import type { FormError, FormSubmitEvent, TableColumn } from '@nuxt/ui';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type Props = {
    id_project: number;
};

type ProjectSummary = {
    id: number;
    name: string;
    slug: string;
};

type ProjectChangelog = {
    id: number;
    project_id: number;
    project_version: string;
    changelog_content: string;
    created_at: string;
    updated_at: string;
    project?: ProjectSummary | null;
};

type PaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
};

type ProjectChangelogResponse = {
    data: ProjectChangelog[];
    meta: PaginationMeta;
};

type ResourceResponse<T> = {
    data: T;
};

type ProjectChangelogFormState = {
    project_version: string;
    changelog_content: string;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

const props = defineProps<Props>();

const columns: TableColumn<ProjectChangelog>[] = [
    {
        accessorKey: 'project_version',
        header: 'Version',
    },
    {
        accessorKey: 'changelog_content',
        header: 'Changelog',
    },
    {
        accessorKey: 'updated_at',
        header: 'Updated',
    },
    {
        id: 'actions',
    },
];

const state = reactive<ProjectChangelogFormState>({
    project_version: '',
    changelog_content: '',
});

const changelogData = ref<ProjectChangelog[]>([]);
const meta = ref<PaginationMeta | null>(null);
const search = ref('');
const currentPage = ref(1);
const isLoading = ref(true);
const isSaving = ref(false);
const isDeleting = ref(false);
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingChangelogId = ref<number | null>(null);
const deletingChangelog = ref<ProjectChangelog | null>(null);
const errorMessage = ref<string | null>(null);
const formMessage = ref<string | null>(null);
const deleteMessage = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});

const isEditing = computed(() => editingChangelogId.value !== null);
const modalTitle = computed(() =>
    isEditing.value ? 'Edit Changelog' : 'Add Changelog',
);
const modalDescription = computed(() =>
    isEditing.value
        ? 'Perbarui catatan perubahan untuk project ini.'
        : 'Tambahkan versi dan catatan perubahan baru.',
);
const submitLabel = computed(() =>
    isEditing.value ? 'Update Changelog' : 'Create Changelog',
);
const deleteModalDescription = computed(() => {
    if (!deletingChangelog.value) {
        return 'Changelog ini akan dihapus secara permanen.';
    }

    return `Changelog versi ${deletingChangelog.value.project_version} akan dihapus secara permanen.`;
});

const filteredChangelogs = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return changelogData.value;
    }

    return changelogData.value.filter((changelog) => {
        const searchableContent = [
            changelog.project_version,
            changelog.changelog_content,
            changelog.project?.name,
            changelog.project?.slug,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return searchableContent.includes(query);
    });
});

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) {
        return '0 changelogs';
    }

    return `${meta.value.from}-${meta.value.to} of ${meta.value.total} changelogs`;
});

const formatDate = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
    }).format(new Date(value));
};

const validate = (
    formState: Partial<ProjectChangelogFormState>,
): FormError[] => {
    const errors: FormError[] = [];

    if (!formState.project_version?.trim()) {
        errors.push({
            name: 'project_version',
            message: 'Versi project wajib diisi.',
        });
    }

    if (!formState.changelog_content?.trim()) {
        errors.push({
            name: 'changelog_content',
            message: 'Isi changelog wajib diisi.',
        });
    }

    return errors;
};

const fieldError = (name: string): string | undefined => {
    return serverErrors.value[name];
};

const fetchChangelogs = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.get<ProjectChangelogResponse>(
            '/ajax/project-changelogs',
            {
                params: {
                    project_id: props.id_project,
                    page,
                    per_page: 100,
                },
            },
        );

        changelogData.value = response.data.data;
        meta.value = response.data.meta;
        currentPage.value = response.data.meta.current_page;
    } catch {
        errorMessage.value = 'Data changelog gagal dimuat.';
    } finally {
        isLoading.value = false;
    }
};

const resetForm = (): void => {
    state.project_version = '';
    state.changelog_content = '';
    editingChangelogId.value = null;
    formMessage.value = null;
    serverErrors.value = {};
};

const openCreateModal = (): void => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (changelog: ProjectChangelog): void => {
    state.project_version = changelog.project_version;
    state.changelog_content = changelog.changelog_content;
    editingChangelogId.value = changelog.id;
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

const buildPayload = (): {
    project_id: number;
    project_version: string;
    changelog_content: string;
} => ({
    project_id: props.id_project,
    project_version: state.project_version.trim(),
    changelog_content: state.changelog_content.trim(),
});

const storeProjectChangelog = async (): Promise<ProjectChangelog> => {
    const response = await axios.post<ResourceResponse<ProjectChangelog>>(
        '/ajax/project-changelogs',
        buildPayload(),
    );

    return response.data.data;
};

const updateProjectChangelog = async (
    id: number,
): Promise<ProjectChangelog> => {
    const response = await axios.patch<ResourceResponse<ProjectChangelog>>(
        `/ajax/project-changelogs/${id}`,
        buildPayload(),
    );

    return response.data.data;
};

const openDeleteModal = (changelog: ProjectChangelog): void => {
    deletingChangelog.value = changelog;
    deleteMessage.value = null;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = (): void => {
    if (isDeleting.value) {
        return;
    }

    isDeleteModalOpen.value = false;
    deletingChangelog.value = null;
    deleteMessage.value = null;
};

const deleteProjectChangelog = async (): Promise<void> => {
    if (!deletingChangelog.value) {
        return;
    }

    isDeleting.value = true;
    deleteMessage.value = null;

    try {
        await axios.delete(
            `/ajax/project-changelogs/${deletingChangelog.value.id}`,
        );

        const targetPage =
            changelogData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;

        isDeleteModalOpen.value = false;
        deletingChangelog.value = null;

        if (targetPage !== currentPage.value) {
            currentPage.value = targetPage;
        } else {
            await fetchChangelogs(targetPage);
        }
    } catch {
        deleteMessage.value = 'Changelog gagal dihapus.';
    } finally {
        isDeleting.value = false;
    }
};

const handleValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        formMessage.value = 'Changelog gagal disimpan.';

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

const submitProjectChangelog = async (
    _event: FormSubmitEvent<ProjectChangelogFormState>,
): Promise<void> => {
    isSaving.value = true;
    formMessage.value = null;
    serverErrors.value = {};

    try {
        if (editingChangelogId.value) {
            await updateProjectChangelog(editingChangelogId.value);
        } else {
            await storeProjectChangelog();
        }

        isModalOpen.value = false;
        resetForm();
        await fetchChangelogs(currentPage.value);
    } catch (error) {
        handleValidationErrors(error);
    } finally {
        isSaving.value = false;
    }
};

watch(currentPage, (page) => {
    if (page !== meta.value?.current_page) {
        void fetchChangelogs(page);
    }
});

watch(
    () => props.id_project,
    () => {
        changelogData.value = [];
        meta.value = null;
        currentPage.value = 1;
        void fetchChangelogs(1);
    },
);

watch(isModalOpen, (open) => {
    if (!open && !isSaving.value) {
        resetForm();
    }
});

watch(isDeleteModalOpen, (open) => {
    if (!open && !isDeleting.value) {
        deletingChangelog.value = null;
        deleteMessage.value = null;
    }
});

onMounted(() => {
    void fetchChangelogs();
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h2 class="text-xl font-semibold text-highlighted">
                    Project Changelogs
                </h2>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Search changelogs..."
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
                    aria-label="Refresh changelogs"
                    @click="fetchChangelogs(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Gagal memuat changelog"
            :description="errorMessage"
            :actions="[
                {
                    label: 'Coba lagi',
                    icon: 'i-lucide-refresh-cw',
                    color: 'error',
                    variant: 'subtle',
                    onClick: () => fetchChangelogs(currentPage),
                },
            ]"
        />

        <div class="overflow-hidden rounded-lg border border-default bg-default">
            <UTable
                :data="filteredChangelogs"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <template #project_version-cell="{ row }">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-highlighted">
                            v{{ row.original.project_version }}
                        </p>
                        <p class="text-xs text-muted">
                            {{ formatDate(row.original.updated_at) }}
                        </p>
                    </div>
                </template>

                <template #changelog_content-cell="{ row }">
                    <p class="max-w-3xl whitespace-pre-line text-sm text-default">
                        {{ row.original.changelog_content }}
                    </p>
                </template>

                <template #updated_at-cell="{ row }">
                    {{ formatDate(row.original.updated_at) }}
                </template>

                <template #actions-cell="{ row }">
                    <div class="flex justify-end gap-1">
                        <UButton
                            icon="i-lucide-pencil"
                            color="neutral"
                            variant="ghost"
                            aria-label="Edit changelog"
                            :disabled="isLoading || isDeleting"
                            @click="openEditModal(row.original)"
                        />

                        <UButton
                            icon="i-lucide-trash"
                            color="error"
                            variant="ghost"
                            aria-label="Delete changelog"
                            :disabled="isLoading || isDeleting"
                            @click="openDeleteModal(row.original)"
                        />
                    </div>
                </template>

                <template #empty>
                    <div class="flex flex-col items-center gap-2 py-10">
                        <UIcon
                            name="i-lucide-scroll-text"
                            class="size-8 text-muted"
                        />
                        <p class="font-medium text-highlighted">
                            Belum ada changelog
                        </p>
                        <p class="text-sm text-muted">
                            Tambahkan versi project dan catatan perubahan
                            pertamanya.
                        </p>
                    </div>
                </template>
            </UTable>

            <div
                v-if="meta && meta.total > meta.per_page"
                class="flex flex-col gap-3 border-t border-default px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>

                <UPagination
                    v-model:page="currentPage"
                    :total="meta.total"
                    :items-per-page="meta.per_page"
                    :disabled="isLoading"
                />
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
                    id="project-changelog-form"
                    :state="state"
                    :validate="validate"
                    class="space-y-4"
                    @submit="submitProjectChangelog"
                >
                    <UFormField
                        name="project_version"
                        label="Project Version"
                        required
                        :error="fieldError('project_version')"
                    >
                        <UInput
                            v-model="state.project_version"
                            placeholder="1.0.0"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="changelog_content"
                        label="Changelog Content"
                        required
                        :error="fieldError('changelog_content')"
                    >
                        <UTextarea
                            v-model="state.changelog_content"
                            :rows="6"
                            placeholder="Tulis daftar perubahan untuk versi ini."
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
                    form="project-changelog-form"
                    icon="i-lucide-save"
                    :label="submitLabel"
                    :loading="isSaving"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isDeleteModalOpen"
            title="Delete Changelog"
            :description="deleteModalDescription"
            :ui="{ footer: 'justify-end' }"
        >
            <template #body>
                <UAlert
                    v-if="deleteMessage"
                    color="error"
                    variant="soft"
                    icon="i-lucide-circle-alert"
                    title="Ada masalah"
                    :description="deleteMessage"
                />
            </template>

            <template #footer>
                <UButton
                    label="Cancel"
                    color="neutral"
                    variant="outline"
                    :disabled="isDeleting"
                    @click="closeDeleteModal"
                />

                <UButton
                    label="Delete"
                    icon="i-lucide-trash"
                    color="error"
                    :loading="isDeleting"
                    @click="deleteProjectChangelog"
                />
            </template>
        </UModal>
    </div>
</template>
