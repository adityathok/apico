<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { FormError, FormSubmitEvent, TableColumn } from '@nuxt/ui';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { categories as categoriesRoute } from '@/routes';

type Category = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    posts_count?: number;
    created_at: string;
    updated_at: string;
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

type CategoriesResponse = {
    data: Category[];
    meta: PaginationMeta;
};

type ResourceResponse<T> = {
    data: T;
};

type CategoryFormState = {
    name: string;
    slug: string;
    description: string;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Categories',
                href: categoriesRoute(),
            },
        ],
    },
});

const columns: TableColumn<Category>[] = [
    {
        accessorKey: 'name',
        header: 'Category',
    },
    {
        accessorKey: 'slug',
        header: 'Slug',
    },
    {
        accessorKey: 'description',
        header: 'Description',
    },
    {
        accessorKey: 'posts_count',
        header: 'Posts',
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
    },
    {
        id: 'actions',
    },
];

const state = reactive<CategoryFormState>({
    name: '',
    slug: '',
    description: '',
});

const categoryData = ref<Category[]>([]);
const meta = ref<PaginationMeta | null>(null);
const search = ref('');
const currentPage = ref(1);
const isLoading = ref(true);
const isSaving = ref(false);
const isDeleting = ref(false);
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingCategoryId = ref<number | null>(null);
const deletingCategory = ref<Category | null>(null);
const errorMessage = ref<string | null>(null);
const formMessage = ref<string | null>(null);
const deleteMessage = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});

const isEditing = computed(() => editingCategoryId.value !== null);
const modalTitle = computed(() =>
    isEditing.value ? 'Edit Category' : 'Create Category',
);
const modalDescription = computed(() =>
    isEditing.value
        ? 'Update detail category yang sudah ada.'
        : 'Tambahkan category baru untuk mengelompokkan post.',
);
const submitLabel = computed(() =>
    isEditing.value ? 'Update Category' : 'Create Category',
);
const deleteModalDescription = computed(() => {
    if (!deletingCategory.value) {
        return 'Category ini akan dihapus secara permanen.';
    }

    return `Category "${deletingCategory.value.name}" akan dihapus secara permanen.`;
});

const filteredCategories = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return categoryData.value;
    }

    return categoryData.value.filter((category) => {
        const searchableContent = [
            category.name,
            category.slug,
            category.description,
            String(category.posts_count ?? 0),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return searchableContent.includes(query);
    });
});

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) {
        return '0 categories';
    }

    return `${meta.value.from}-${meta.value.to} of ${meta.value.total} categories`;
});

const fetchCategories = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.get<CategoriesResponse>(
            '/api/categories',
            {
                params: { page },
            },
        );

        categoryData.value = response.data.data;
        meta.value = response.data.meta;
        currentPage.value = response.data.meta.current_page;
    } catch {
        errorMessage.value = 'Data category gagal dimuat.';
    } finally {
        isLoading.value = false;
    }
};

const slugify = (value: string): string => {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
    }).format(new Date(value));
};

const validate = (formState: Partial<CategoryFormState>): FormError[] => {
    const errors: FormError[] = [];

    if (!formState.name?.trim()) {
        errors.push({ name: 'name', message: 'Name wajib diisi.' });
    }

    if (!formState.slug?.trim()) {
        errors.push({ name: 'slug', message: 'Slug wajib diisi.' });
    }

    return errors;
};

const fieldError = (name: string): string | undefined => {
    return serverErrors.value[name];
};

const resetForm = (): void => {
    state.name = '';
    state.slug = '';
    state.description = '';
    editingCategoryId.value = null;
    formMessage.value = null;
    serverErrors.value = {};
};

const openCreateModal = (): void => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (category: Category): void => {
    state.name = category.name;
    state.slug = category.slug;
    state.description = category.description ?? '';
    editingCategoryId.value = category.id;
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

const buildPayload = (): CategoryFormState => ({
    name: state.name,
    slug: state.slug,
    description: state.description,
});

const storeCategory = async (): Promise<Category> => {
    const response = await axios.post<ResourceResponse<Category>>(
        '/api/categories',
        buildPayload(),
    );

    return response.data.data;
};

const updateCategory = async (id: number): Promise<Category> => {
    const response = await axios.patch<ResourceResponse<Category>>(
        `/api/categories/${id}`,
        buildPayload(),
    );

    return response.data.data;
};

const openDeleteModal = (category: Category): void => {
    deletingCategory.value = category;
    deleteMessage.value = null;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = (): void => {
    if (isDeleting.value) {
        return;
    }

    isDeleteModalOpen.value = false;
    deletingCategory.value = null;
    deleteMessage.value = null;
};

const deleteCategory = async (): Promise<void> => {
    if (!deletingCategory.value) {
        return;
    }

    isDeleting.value = true;
    deleteMessage.value = null;

    try {
        await axios.delete(`/api/categories/${deletingCategory.value.id}`);

        const targetPage =
            categoryData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;

        isDeleteModalOpen.value = false;
        deletingCategory.value = null;

        if (targetPage !== currentPage.value) {
            currentPage.value = targetPage;
        } else {
            await fetchCategories(targetPage);
        }
    } catch {
        deleteMessage.value = 'Category gagal dihapus.';
    } finally {
        isDeleting.value = false;
    }
};

const handleValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        formMessage.value = 'Category gagal disimpan.';

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

const submitCategory = async (
    _event: FormSubmitEvent<CategoryFormState>,
): Promise<void> => {
    isSaving.value = true;
    formMessage.value = null;
    serverErrors.value = {};

    try {
        if (editingCategoryId.value) {
            await updateCategory(editingCategoryId.value);
        } else {
            await storeCategory();
        }

        isModalOpen.value = false;
        resetForm();
        await fetchCategories(currentPage.value);
    } catch (error) {
        handleValidationErrors(error);
    } finally {
        isSaving.value = false;
    }
};

watch(currentPage, (page) => {
    if (page !== meta.value?.current_page) {
        void fetchCategories(page);
    }
});

watch(
    () => state.name,
    (nameValue) => {
        if (!isEditing.value && state.slug === '') {
            state.slug = slugify(nameValue);
        }
    },
);

watch(isModalOpen, (open) => {
    if (!open && !isSaving.value) {
        resetForm();
    }
});

watch(isDeleteModalOpen, (open) => {
    if (!open && !isDeleting.value) {
        deletingCategory.value = null;
        deleteMessage.value = null;
    }
});

onMounted(() => {
    void fetchCategories();
});
</script>

<template>
    <Head title="Categories" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">
                    Categories
                </h1>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Search categories..."
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
                    aria-label="Refresh categories"
                    @click="fetchCategories(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Gagal memuat category"
            :description="errorMessage"
            :actions="[
                {
                    label: 'Coba lagi',
                    icon: 'i-lucide-refresh-cw',
                    color: 'error',
                    variant: 'subtle',
                    onClick: () => fetchCategories(currentPage),
                },
            ]"
        />

        <div
            class="overflow-hidden rounded-lg border border-default bg-default"
        >
            <UTable
                :data="filteredCategories"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <template #name-cell="{ row }">
                    <div class="flex items-center gap-3">
                        <UAvatar
                            :alt="row.original.name"
                            icon="i-lucide-folder"
                            size="lg"
                        />

                        <div class="min-w-0">
                            <p class="truncate font-medium text-highlighted">
                                {{ row.original.name }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #slug-cell="{ row }">
                    <UBadge
                        color="neutral"
                        variant="subtle"
                        :label="row.original.slug"
                    />
                </template>

                <template #description-cell="{ row }">
                    <p class="max-w-md truncate text-sm text-muted">
                        {{ row.original.description || '-' }}
                    </p>
                </template>

                <template #posts_count-cell="{ row }">
                    <UBadge
                        color="primary"
                        variant="subtle"
                        :label="String(row.original.posts_count ?? 0)"
                    />
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
                            aria-label="Edit category"
                            :disabled="isLoading || isDeleting"
                            @click="openEditModal(row.original)"
                        />

                        <UButton
                            icon="i-lucide-trash"
                            color="error"
                            variant="ghost"
                            aria-label="Delete category"
                            :disabled="isLoading || isDeleting"
                            @click="openDeleteModal(row.original)"
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
                            Tidak ada category
                        </p>
                        <p class="text-sm text-muted">
                            Data belum tersedia atau tidak cocok dengan
                            pencarian.
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
                    id="category-form"
                    :state="state"
                    :validate="validate"
                    class="space-y-4"
                    @submit="submitCategory"
                >
                    <UFormField
                        name="name"
                        label="Name"
                        required
                        :error="fieldError('name')"
                    >
                        <UInput
                            v-model="state.name"
                            placeholder="Category name"
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
                            placeholder="category-slug"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="description"
                        label="Description"
                        hint="Optional"
                        :error="fieldError('description')"
                    >
                        <UTextarea
                            v-model="state.description"
                            placeholder="Short category description"
                            :rows="4"
                            autoresize
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
                    form="category-form"
                    icon="i-lucide-save"
                    :label="submitLabel"
                    :loading="isSaving"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isDeleteModalOpen"
            title="Delete Category"
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
                    @click="deleteCategory"
                />
            </template>
        </UModal>
    </div>
</template>
