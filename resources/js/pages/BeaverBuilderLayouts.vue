<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { FormError, FormSubmitEvent, TableColumn } from '@nuxt/ui';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type Category = {
    id: number;
    name: string;
};

type Layout = {
    id: number;
    title: string;
    type: string;
    content: string;
    meta: Record<string, unknown> | null;
    screenshot: string | null;
    categories: Category[];
    created_at: string;
    updated_at: string;
};

type PaginationMeta = {
    current_page: number;
    from: number | null;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
    path: string;
    per_page: number;
    to: number | null;
    total: number;
};

type LayoutsResponse = {
    data: Layout[];
    meta: PaginationMeta;
};

type ResourceResponse<T> = {
    data: T;
};

type LayoutFormState = {
    title: string;
    type: string;
    content: string;
    meta: string;
    screenshot: string;
};

type CategoriesResponse = {
    data: Category[];
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Beaver Builder Layouts',
            },
        ],
    },
});

const typeOptions = [
    { label: 'Theme Layout', value: 'theme-layout' },
    { label: 'Template Layout', value: 'template-layout' },
    { label: 'Row', value: 'row' },
    { label: 'Module', value: 'module' },
];

const columns: TableColumn<Layout>[] = [
    {
        accessorKey: 'title',
        header: 'Title',
    },
    {
        accessorKey: 'type',
        header: 'Type',
    },
    {
        accessorKey: 'screenshot',
        header: 'Screenshot',
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
    },
    {
        id: 'actions',
    },
];

const state = reactive<LayoutFormState>({
    title: '',
    type: 'theme-layout',
    content: '',
    meta: '',
    screenshot: '',
});

const layoutData = ref<Layout[]>([]);
const meta = ref<PaginationMeta | null>(null);
const categories = ref<Category[]>([]);
const selectedCategoryIds = ref<number[]>([]);
const search = ref('');
const currentPage = ref(1);
const isLoading = ref(true);
const isSaving = ref(false);
const isDeleting = ref(false);
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingLayoutId = ref<number | null>(null);
const deletingLayout = ref<Layout | null>(null);
const errorMessage = ref<string | null>(null);
const formMessage = ref<string | null>(null);
const deleteMessage = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});

const isEditing = computed(() => editingLayoutId.value !== null);
const modalTitle = computed(() =>
    isEditing.value ? 'Edit Layout' : 'Create Layout',
);
const modalDescription = computed(() =>
    isEditing.value
        ? 'Update detail layout yang sudah ada.'
        : 'Tambahkan layout baru.',
);
const submitLabel = computed(() =>
    isEditing.value ? 'Update Layout' : 'Create Layout',
);
const deleteModalDescription = computed(() => {
    if (!deletingLayout.value) {
        return 'Layout ini akan dihapus secara permanen.';
    }
    return `Layout "${deletingLayout.value.title}" akan dihapus secara permanen.`;
});

const filteredLayouts = computed(() => {
    const query = search.value.trim().toLowerCase();
    if (query === '') return layoutData.value;

    return layoutData.value.filter((layout) => {
        const searchable = [layout.title, layout.type, layout.screenshot ?? '']
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
        return searchable.includes(query);
    });
});

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) return '0 layouts';
    return `${meta.value.from}-${meta.value.to} of ${meta.value.total} layouts`;
});

const typeBadgeColor = (type: string): string => {
    switch (type) {
        case 'theme-layout':
            return 'primary';
        case 'template-layout':
            return 'info';
        case 'row':
            return 'warning';
        case 'module':
            return 'success';
        default:
            return 'neutral';
    }
};

const typeLabel = (type: string): string => {
    const opt = typeOptions.find((o) => o.value === type);
    return opt?.label ?? type;
};

const fetchLayouts = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;
    try {
        const res = await axios.get<LayoutsResponse>('/ajax/beaver-builder-layouts', {
            params: { page },
        });
        layoutData.value = res.data.data;
        meta.value = res.data.meta;
        currentPage.value = res.data.meta.current_page;
    } catch {
        errorMessage.value = 'Data layout gagal dimuat.';
    } finally {
        isLoading.value = false;
    }
};

const fetchCategories = async (): Promise<void> => {
    try {
        const res = await axios.get<CategoriesResponse>('/ajax/beaver-builder-template-categories');
        categories.value = res.data.data;
    } catch {
        // silently fail
    }
};

const formatDate = (value: string | null): string => {
    if (!value) return '-';
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value));
};

const validate = (formState: Partial<LayoutFormState>): FormError[] => {
    const errors: FormError[] = [];
    if (!formState.title?.trim()) errors.push({ name: 'title', message: 'Title wajib diisi.' });
    if (!formState.type) errors.push({ name: 'type', message: 'Type wajib dipilih.' });
    if (!formState.content?.trim()) errors.push({ name: 'content', message: 'Content wajib diisi.' });
    return errors;
};

const fieldError = (name: string): string | undefined => serverErrors.value[name];

const resetForm = (): void => {
    state.title = '';
    state.type = 'theme-layout';
    state.content = '';
    state.meta = '';
    state.screenshot = '';
    selectedCategoryIds.value = [];
    editingLayoutId.value = null;
    formMessage.value = null;
    serverErrors.value = {};
};

const openCreateModal = (): void => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (layout: Layout): void => {
    state.title = layout.title;
    state.type = layout.type;
    state.content = layout.content;
    state.meta = layout.meta ? JSON.stringify(layout.meta, null, 2) : '';
    state.screenshot = layout.screenshot ?? '';
    selectedCategoryIds.value = layout.categories.map((c) => c.id);
    editingLayoutId.value = layout.id;
    formMessage.value = null;
    serverErrors.value = {};
    isModalOpen.value = true;
};

const closeModal = (): void => {
    if (isSaving.value) return;
    isModalOpen.value = false;
    resetForm();
};

const parseMeta = (): Record<string, unknown> | null => {
    const trimmed = state.meta.trim();
    if (!trimmed) return null;
    try {
        return JSON.parse(trimmed);
    } catch {
        return null;
    }
};

const buildPayload = () => ({
    title: state.title,
    type: state.type,
    content: state.content,
    meta: parseMeta(),
    screenshot: state.screenshot || null,
    category_ids: selectedCategoryIds.value,
});

const storeLayout = async (): Promise<Layout> => {
    const res = await axios.post<ResourceResponse<Layout>>(
        '/ajax/beaver-builder-layouts',
        buildPayload(),
    );
    return res.data.data;
};

const updateLayout = async (id: number): Promise<Layout> => {
    const res = await axios.patch<ResourceResponse<Layout>>(
        `/ajax/beaver-builder-layouts/${id}`,
        buildPayload(),
    );
    return res.data.data;
};

const openDeleteModal = (layout: Layout): void => {
    deletingLayout.value = layout;
    deleteMessage.value = null;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = (): void => {
    if (isDeleting.value) return;
    isDeleteModalOpen.value = false;
    deletingLayout.value = null;
    deleteMessage.value = null;
};

const deleteLayout = async (): Promise<void> => {
    if (!deletingLayout.value) return;
    isDeleting.value = true;
    deleteMessage.value = null;
    try {
        await axios.delete(`/ajax/beaver-builder-layouts/${deletingLayout.value.id}`);
        const targetPage =
            layoutData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;
        isDeleteModalOpen.value = false;
        deletingLayout.value = null;
        if (targetPage !== currentPage.value) {
            currentPage.value = targetPage;
        } else {
            await fetchLayouts(targetPage);
        }
    } catch {
        deleteMessage.value = 'Layout gagal dihapus.';
    } finally {
        isDeleting.value = false;
    }
};

const handleValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        formMessage.value = 'Layout gagal disimpan.';
        return;
    }
    const response = error.response.data as ValidationResponse;
    const errors = response.errors ?? {};
    serverErrors.value = Object.fromEntries(
        Object.entries(errors).map(([name, messages]) => [name, messages[0] ?? 'Invalid value.']),
    );
};

const submitLayout = async (_event: FormSubmitEvent<LayoutFormState>): Promise<void> => {
    isSaving.value = true;
    formMessage.value = null;
    serverErrors.value = {};

    // validate meta JSON
    if (state.meta.trim()) {
        try {
            JSON.parse(state.meta);
        } catch {
            serverErrors.value.meta = 'Meta harus berupa JSON yang valid.';
            isSaving.value = false;
            return;
        }
    }

    try {
        if (editingLayoutId.value) {
            await updateLayout(editingLayoutId.value);
        } else {
            await storeLayout();
        }
        isModalOpen.value = false;
        resetForm();
        await fetchLayouts(currentPage.value);
    } catch (error) {
        handleValidationErrors(error);
    } finally {
        isSaving.value = false;
    }
};

watch(currentPage, (page) => {
    if (page !== meta.value?.current_page) {
        void fetchLayouts(page);
    }
});

watch(isModalOpen, (open) => {
    if (!open && !isSaving.value) resetForm();
});

watch(isDeleteModalOpen, (open) => {
    if (!open && !isDeleting.value) {
        deletingLayout.value = null;
        deleteMessage.value = null;
    }
});

onMounted(() => {
    void fetchLayouts();
    void fetchCategories();
});
</script>

<template>
    <Head title="Beaver Builder Layouts" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">
                    Beaver Builder Layouts
                </h1>
                <p class="text-sm text-muted">{{ paginationSummary }}</p>
            </div>

            <div class="flex items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Search layouts..."
                    :disabled="isLoading"
                    class="w-full sm:w-64"
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
                    aria-label="Refresh"
                    @click="fetchLayouts(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Gagal memuat layout"
            :description="errorMessage"
            :actions="[
                {
                    label: 'Coba lagi',
                    icon: 'i-lucide-refresh-cw',
                    color: 'error',
                    variant: 'subtle',
                    onClick: () => fetchLayouts(currentPage),
                },
            ]"
        />

        <div class="overflow-hidden rounded-lg border border-default bg-default">
            <UTable
                :data="filteredLayouts"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <template #title-cell="{ row }">
                    <div class="flex items-center gap-3">
                        <UAvatar
                            :alt="row.original.title"
                            icon="i-lucide-layout-dashboard"
                            size="lg"
                        />
                        <div class="min-w-0">
                            <p class="truncate font-medium text-highlighted">
                                {{ row.original.title }}
                            </p>
                            <p v-if="row.original.categories?.length" class="text-xs text-muted">
                                {{ row.original.categories.map((c: Category) => c.name).join(', ') }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #type-cell="{ row }">
                    <UBadge
                        :color="typeBadgeColor(row.original.type)"
                        variant="subtle"
                        :label="typeLabel(row.original.type)"
                    />
                </template>

                <template #screenshot-cell="{ row }">
                    <span v-if="row.original.screenshot" class="text-sm text-muted truncate max-w-32 block">
                        {{ row.original.screenshot }}
                    </span>
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
                            aria-label="Edit"
                            :disabled="isLoading || isDeleting"
                            @click="openEditModal(row.original)"
                        />
                        <UButton
                            icon="i-lucide-trash"
                            color="error"
                            variant="ghost"
                            aria-label="Delete"
                            :disabled="isLoading || isDeleting"
                            @click="openDeleteModal(row.original)"
                        />
                    </div>
                </template>

                <template #empty>
                    <div class="flex flex-col items-center gap-2 py-10">
                        <UIcon name="i-lucide-inbox" class="size-8 text-muted" />
                        <p class="font-medium text-highlighted">Tidak ada layout</p>
                        <p class="text-sm text-muted">Data belum tersedia atau tidak cocok dengan pencarian.</p>
                    </div>
                </template>
            </UTable>

            <div
                v-if="meta && meta.total > meta.per_page"
                class="flex flex-col gap-3 border-t border-default px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-muted">{{ paginationSummary }}</p>
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
                    id="layout-form"
                    :state="state"
                    :validate="validate"
                    class="space-y-4"
                    @submit="submitLayout"
                >
                    <UFormField name="title" label="Title" required :error="fieldError('title')">
                        <UInput
                            v-model="state.title"
                            placeholder="Layout title"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField name="type" label="Type" required :error="fieldError('type')">
                        <USelect
                            v-model="state.type"
                            :items="typeOptions"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField name="content" label="Content" required :error="fieldError('content')">
                        <UTextarea
                            v-model="state.content"
                            placeholder="Layout content (HTML/BB)"
                            :rows="8"
                            autoresize
                            :disabled="isSaving"
                            class="w-full font-mono text-xs"
                        />
                    </UFormField>

                    <UFormField name="meta" label="Meta" hint="JSON, optional" :error="fieldError('meta')">
                        <UTextarea
                            v-model="state.meta"
                            placeholder='{"key": "value"}'
                            :rows="4"
                            autoresize
                            :disabled="isSaving"
                            class="w-full font-mono text-xs"
                        />
                    </UFormField>

                    <UFormField name="screenshot" label="Screenshot" hint="Optional" :error="fieldError('screenshot')">
                        <UInput
                            v-model="state.screenshot"
                            placeholder="screenshot.jpg"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField name="category_ids" label="Categories" hint="Optional">
                        <USelect
                            v-model="selectedCategoryIds"
                            :items="categories.map((c) => ({ label: c.name, value: c.id }))"
                            :disabled="isSaving"
                            multiple
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
                    form="layout-form"
                    icon="i-lucide-save"
                    :label="submitLabel"
                    :loading="isSaving"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isDeleteModalOpen"
            title="Delete Layout"
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
                    @click="deleteLayout"
                />
            </template>
        </UModal>
    </div>
</template>
