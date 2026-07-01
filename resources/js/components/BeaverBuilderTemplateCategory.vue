<script setup lang="ts">
import type { FormError, TableColumn } from '@nuxt/ui';
import axios, { AxiosError } from 'axios';
import { computed, nextTick, onMounted, reactive, ref } from 'vue';

type Category = {
    id: number;
    name: string;
    parent_id: number | null;
    parent: Category | null;
    children: Category[];
    layouts_count?: number;
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

type CategoriesResponse = {
    data: Category[];
    meta: PaginationMeta;
};

type ResourceResponse<T> = {
    data: T;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

const emit = defineEmits<{
    refresh: [];
}>();

const columns: TableColumn<Category>[] = [
    { accessorKey: 'name', header: 'Name' },
    { accessorKey: 'parent', header: 'Parent' },
    { accessorKey: 'layouts_count', header: 'Layouts' },
    { accessorKey: 'created_at', header: 'Created' },
    { id: 'actions' },
];

const categoryData = ref<Category[]>([]);
const meta = ref<PaginationMeta | null>(null);
const allCategories = ref<Category[]>([]);
const search = ref('');
const currentPage = ref(1);
const isLoading = ref(true);
const isSaving = ref(false);
const isDeleting = ref(false);
const isDeleteModalOpen = ref(false);
const deletingCategory = ref<Category | null>(null);
const deleteMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});

// inline create
const isAdding = ref(false);
const newName = ref('');
const newParentId = ref<number | null>(null);
const newNameError = ref('');

// inline edit
const editingId = ref<number | null>(null);
const editName = ref('');
const editParentId = ref<number | null>(null);
const editNameError = ref('');

const allCategoryOptions = computed(() =>
    allCategories.value.map((c) => ({ label: c.name, value: c.id })),
);

const parentOptionsForEdit = computed(() =>
    allCategories.value
        .filter((c) => c.id !== editingId.value)
        .map((c) => ({ label: c.name, value: c.id })),
);

const filteredCategories = computed(() => {
    const query = search.value.trim().toLowerCase();
    if (query === '') return categoryData.value;
    return categoryData.value.filter((category) => {
        const searchable = [category.name, category.parent?.name ?? '']
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
        return searchable.includes(query);
    });
});

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) return '0 categories';
    return `${meta.value.from}-${meta.value.to} of ${meta.value.total} categories`;
});

const fetchCategories = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;
    try {
        const res = await axios.get<CategoriesResponse>(
            '/ajax/beaver-builder-template-categories',
            { params: { page } },
        );
        categoryData.value = res.data.data;
        meta.value = res.data.meta;
        currentPage.value = res.data.meta.current_page;
    } catch {
        errorMessage.value = 'Data kategori gagal dimuat.';
    } finally {
        isLoading.value = false;
    }
};

const fetchAllCategories = async (): Promise<void> => {
    try {
        const res = await axios.get<CategoriesResponse>(
            '/ajax/beaver-builder-template-categories',
            { params: { per_page: 1000 } },
        );
        allCategories.value = res.data.data;
    } catch {
        // silently fail
    }
};

const formatDate = (value: string | null): string => {
    if (!value) return '-';
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(new Date(value));
};

// --- Inline Add ---
const cancelAdd = (): void => {
    isAdding.value = false;
    newName.value = '';
    newParentId.value = null;
    newNameError.value = '';
};

const startAdd = (): void => {
    cancelAdd();
    isAdding.value = true;
    nextTick(() => {
        document.getElementById('inline-new-name')?.focus();
    });
};

const saveNew = async (): Promise<void> => {
    newNameError.value = '';
    if (!newName.value.trim()) {
        newNameError.value = 'Name wajib diisi.';
        return;
    }
    isSaving.value = true;
    try {
        const res = await axios.post<ResourceResponse<Category>>(
            '/ajax/beaver-builder-template-categories',
            { name: newName.value, parent_id: newParentId.value },
        );
        categoryData.value.unshift(res.data.data);
        cancelAdd();
        await fetchAllCategories();
        emit('refresh');
    } catch (error) {
        if (error instanceof AxiosError && error.response?.status === 422) {
            const data = error.response.data as ValidationResponse;
            const errors = data.errors ?? {};
            newNameError.value = errors.name?.[0] ?? '';
            if (!newNameError.value) {
                newNameError.value = 'Gagal menyimpan.';
            }
        } else {
            newNameError.value = 'Gagal menyimpan kategori.';
        }
    } finally {
        isSaving.value = false;
    }
};

// --- Inline Edit ---
const startEdit = (category: Category): void => {
    cancelAdd();
    editingId.value = category.id;
    editName.value = category.name;
    editParentId.value = category.parent_id;
    editNameError.value = '';
    nextTick(() => {
        document.getElementById(`inline-edit-name-${category.id}`)?.focus();
    });
};

const cancelEdit = (): void => {
    editingId.value = null;
    editName.value = '';
    editParentId.value = null;
    editNameError.value = '';
};

const saveEdit = async (id: number): Promise<void> => {
    editNameError.value = '';
    if (!editName.value.trim()) {
        editNameError.value = 'Name wajib diisi.';
        return;
    }
    isSaving.value = true;
    try {
        const res = await axios.patch<ResourceResponse<Category>>(
            `/ajax/beaver-builder-template-categories/${id}`,
            { name: editName.value, parent_id: editParentId.value },
        );
        const idx = categoryData.value.findIndex((c) => c.id === id);
        if (idx !== -1) categoryData.value[idx] = res.data.data;
        cancelEdit();
        await fetchAllCategories();
        emit('refresh');
    } catch (error) {
        if (error instanceof AxiosError && error.response?.status === 422) {
            const data = error.response.data as ValidationResponse;
            const errors = data.errors ?? {};
            editNameError.value = errors.name?.[0] ?? '';
            if (!editNameError.value) editNameError.value = 'Gagal menyimpan.';
        } else {
            editNameError.value = 'Gagal menyimpan kategori.';
        }
    } finally {
        isSaving.value = false;
    }
};

// --- Delete ---
const openDeleteModal = (category: Category): void => {
    deletingCategory.value = category;
    deleteMessage.value = null;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = (): void => {
    if (isDeleting.value) return;
    isDeleteModalOpen.value = false;
    deletingCategory.value = null;
    deleteMessage.value = null;
};

const deleteCategory = async (): Promise<void> => {
    if (!deletingCategory.value) return;
    isDeleting.value = true;
    deleteMessage.value = null;
    try {
        await axios.delete(`/ajax/beaver-builder-template-categories/${deletingCategory.value.id}`);
        const targetPage =
            categoryData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;
        isDeleteModalOpen.value = false;
        deletingCategory.value = null;
        cancelEdit();
        cancelAdd();
        if (targetPage !== currentPage.value) {
            currentPage.value = targetPage;
        } else {
            await fetchCategories(targetPage);
        }
        await fetchAllCategories();
        emit('refresh');
    } catch {
        deleteMessage.value = 'Category gagal dihapus.';
    } finally {
        isDeleting.value = false;
    }
};

defineExpose({ fetchCategories, fetchAllCategories });

onMounted(() => {
    void fetchCategories();
    void fetchAllCategories();
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold text-highlighted">Template Categories</h1>
                <p class="text-sm text-muted">{{ paginationSummary }}</p>
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
                    :disabled="isLoading || isAdding"
                    @click="startAdd"
                />
                <UButton
                    icon="i-lucide-refresh-cw"
                    color="neutral"
                    variant="outline"
                    :loading="isLoading"
                    aria-label="Refresh"
                    @click="fetchCategories(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Gagal memuat kategori"
            :description="errorMessage"
            :actions="[{
                label: 'Coba lagi',
                icon: 'i-lucide-refresh-cw',
                color: 'error',
                variant: 'subtle',
                onClick: () => fetchCategories(currentPage),
            }]"
        />

        <div class="overflow-hidden rounded-lg border border-default bg-default">
            <UTable
                :data="filteredCategories"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <!-- Inline add row -->
                <template #top>
                    <tr v-if="isAdding" class="bg-muted/50">
                        <td class="p-2 pl-4">
                            <div class="flex items-center gap-2">
                                <UAvatar icon="i-lucide-folder-tree" size="lg" class="shrink-0" />
                                <div class="min-w-0 flex-1">
                                    <UInput
                                        id="inline-new-name"
                                        v-model="newName"
                                        placeholder="Category name"
                                        :disabled="isSaving"
                                        :class="{ 'border-error': newNameError }"
                                        class="w-full"
                                    />
                                    <p v-if="newNameError" class="mt-0.5 text-xs text-error">
                                        {{ newNameError }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="p-2">
                            <USelect
                                v-model="newParentId"
                                :items="[{ label: 'None (root)', value: null }, ...allCategoryOptions]"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </td>
                        <td class="p-2 text-sm text-muted">-</td>
                        <td class="p-2 text-sm text-muted">-</td>
                        <td class="p-2 pr-4">
                            <div class="flex justify-end gap-1">
                                <UButton
                                    icon="i-lucide-check"
                                    color="primary"
                                    variant="solid"
                                    size="sm"
                                    :loading="isSaving"
                                    :disabled="isSaving"
                                    @click="saveNew"
                                />
                                <UButton
                                    icon="i-lucide-x"
                                    color="neutral"
                                    variant="ghost"
                                    size="sm"
                                    :disabled="isSaving"
                                    @click="cancelAdd"
                                />
                            </div>
                        </td>
                    </tr>
                </template>

                <template #name-cell="{ row }">
                    <template v-if="editingId === row.original.id">
                        <div class="min-w-0">
                            <UInput
                                :id="`inline-edit-name-${row.original.id}`"
                                v-model="editName"
                                placeholder="Category name"
                                :disabled="isSaving"
                                :class="{ 'border-error': editNameError }"
                                class="w-full"
                            />
                            <p v-if="editNameError" class="mt-0.5 text-xs text-error">{{ editNameError }}</p>
                        </div>
                    </template>
                    <template v-else>
                        <div class="flex items-center gap-3">
                            <UAvatar
                                :alt="row.original.name"
                                icon="i-lucide-folder-tree"
                                size="lg"
                            />
                            <div class="min-w-0">
                                <p class="truncate font-medium text-highlighted">{{ row.original.name }}</p>
                            </div>
                        </div>
                    </template>
                </template>

                <template #parent-cell="{ row }">
                    <template v-if="editingId === row.original.id">
                        <USelect
                            v-model="editParentId"
                            :items="[{ label: 'None (root)', value: null }, ...parentOptionsForEdit]"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </template>
                    <template v-else>
                        <span v-if="row.original.parent" class="text-sm text-muted">{{ row.original.parent.name }}</span>
                        <span v-else class="text-sm text-muted">-</span>
                    </template>
                </template>

                <template #layouts_count-cell="{ row }">
                    <UBadge color="primary" variant="subtle" :label="String(row.original.layouts_count ?? 0)" />
                </template>

                <template #created_at-cell="{ row }">
                    <template v-if="editingId === row.original.id">
                        <span class="text-sm text-muted">{{ formatDate(row.original.created_at) }}</span>
                    </template>
                    <template v-else>
                        {{ formatDate(row.original.created_at) }}
                    </template>
                </template>

                <template #actions-cell="{ row }">
                    <template v-if="editingId === row.original.id">
                        <div class="flex justify-end gap-1">
                            <UButton
                                icon="i-lucide-check"
                                color="primary"
                                variant="solid"
                                size="sm"
                                :loading="isSaving"
                                :disabled="isSaving"
                                @click="saveEdit(row.original.id)"
                            />
                            <UButton
                                icon="i-lucide-x"
                                color="neutral"
                                variant="ghost"
                                size="sm"
                                :disabled="isSaving"
                                @click="cancelEdit"
                            />
                        </div>
                    </template>
                    <template v-else>
                        <div class="flex justify-end gap-1">
                            <UButton
                                icon="i-lucide-pencil"
                                color="neutral"
                                variant="ghost"
                                aria-label="Edit"
                                :disabled="isLoading || isDeleting || (editingId !== null)"
                                @click="startEdit(row.original)"
                            />
                            <UButton
                                icon="i-lucide-trash"
                                color="error"
                                variant="ghost"
                                aria-label="Delete"
                                :disabled="isLoading || isDeleting || (editingId !== null)"
                                @click="openDeleteModal(row.original)"
                            />
                        </div>
                    </template>
                </template>

                <template #empty>
                    <div class="flex flex-col items-center gap-2 py-10">
                        <UIcon name="i-lucide-inbox" class="size-8 text-muted" />
                        <p class="font-medium text-highlighted">Tidak ada kategori</p>
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
            v-model:open="isDeleteModalOpen"
            title="Delete Template Category"
            :description="deletingCategory
                ? `Category &quot;${deletingCategory.name}&quot; akan dihapus secara permanen.`
                : 'Category ini akan dihapus secara permanen.'"
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
                <UButton label="Cancel" color="neutral" variant="outline" :disabled="isDeleting" @click="closeDeleteModal" />
                <UButton label="Delete" icon="i-lucide-trash" color="error" :loading="isDeleting" @click="deleteCategory" />
            </template>
        </UModal>
    </div>
</template>
