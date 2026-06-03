<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { FormError, FormSubmitEvent, TableColumn } from '@nuxt/ui';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { licences as licencesRoute } from '@/routes';

type LicenseUser = {
    id: number;
    name: string;
    email: string;
};

type License = {
    id: number;
    user_id: number | null;
    code: string;
    is_active: boolean;
    used_at: string | null;
    expires_at: string | null;
    created_at: string;
    updated_at: string;
    user?: LicenseUser | null;
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

type LicensesResponse = {
    data: License[];
    meta: PaginationMeta;
};

type ResourceResponse<T> = {
    data: T;
};

type LicenseFormState = {
    user_id: string;
    code: string;
    is_active: boolean;
    used_at: string;
    expires_at: string;
};

type LicensePayload = {
    user_id: number | null;
    code: string;
    is_active: boolean;
    used_at: string | null;
    expires_at: string | null;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Licences',
                href: licencesRoute(),
            },
        ],
    },
});

const columns: TableColumn<License>[] = [
    {
        accessorKey: 'code',
        header: 'License',
    },
    {
        accessorKey: 'user',
        header: 'User',
    },
    {
        accessorKey: 'is_active',
        header: 'Status',
    },
    {
        accessorKey: 'used_at',
        header: 'Used',
    },
    {
        accessorKey: 'expires_at',
        header: 'Expires',
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
    },
    {
        id: 'actions',
    },
];

const state = reactive<LicenseFormState>({
    user_id: '',
    code: '',
    is_active: true,
    used_at: '',
    expires_at: '',
});

const licenseData = ref<License[]>([]);
const meta = ref<PaginationMeta | null>(null);
const search = ref('');
const currentPage = ref(1);
const isLoading = ref(true);
const isSaving = ref(false);
const isDeleting = ref(false);
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingLicenseId = ref<number | null>(null);
const deletingLicense = ref<License | null>(null);
const errorMessage = ref<string | null>(null);
const formMessage = ref<string | null>(null);
const deleteMessage = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});

const isEditing = computed(() => editingLicenseId.value !== null);
const modalTitle = computed(() =>
    isEditing.value ? 'Edit License' : 'Create License',
);
const modalDescription = computed(() =>
    isEditing.value
        ? 'Update detail license yang sudah ada.'
        : 'Tambahkan license baru untuk user.',
);
const submitLabel = computed(() =>
    isEditing.value ? 'Update License' : 'Create License',
);
const deleteModalDescription = computed(() => {
    if (!deletingLicense.value) {
        return 'License ini akan dihapus secara permanen.';
    }

    return `License "${deletingLicense.value.code}" akan dihapus secara permanen.`;
});

const filteredLicenses = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return licenseData.value;
    }

    return licenseData.value.filter((license) => {
        const searchableContent = [
            license.code,
            license.user?.name,
            license.user?.email,
            license.user_id ? String(license.user_id) : null,
            license.is_active ? 'active' : 'inactive',
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return searchableContent.includes(query);
    });
});

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) {
        return '0 licences';
    }

    return `${meta.value.from}-${meta.value.to} of ${meta.value.total} licences`;
});

const fetchLicenses = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.get<LicensesResponse>('/api/licenses', {
            params: { page },
        });

        licenseData.value = response.data.data;
        meta.value = response.data.meta;
        currentPage.value = response.data.meta.current_page;
    } catch {
        errorMessage.value = 'Data license gagal dimuat.';
    } finally {
        isLoading.value = false;
    }
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

const toDateTimeLocal = (value: string | null): string => {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const timezoneOffset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - timezoneOffset).toISOString().slice(0, 16);
};

const validate = (formState: Partial<LicenseFormState>): FormError[] => {
    const errors: FormError[] = [];

    if (!formState.code?.trim()) {
        errors.push({ name: 'code', message: 'Code wajib diisi.' });
    }

    if (
        formState.user_id &&
        !Number.isInteger(Number(formState.user_id.trim()))
    ) {
        errors.push({ name: 'user_id', message: 'User ID harus berupa angka.' });
    }

    return errors;
};

const fieldError = (name: string): string | undefined => {
    return serverErrors.value[name];
};

const generateCode = (): void => {
    const characters = 'BCDFGHJKLMNPQRSTVWXYZ123456789bcdfghjklmnprstvwx';
    const values = new Uint32Array(20);
    window.crypto.getRandomValues(values);

    state.code = Array.from(values, (value) => {
        return characters[value % characters.length];
    }).join('');
};

const resetForm = (): void => {
    state.user_id = '';
    state.code = '';
    state.is_active = true;
    state.used_at = '';
    state.expires_at = '';
    editingLicenseId.value = null;
    formMessage.value = null;
    serverErrors.value = {};
};

const openCreateModal = (): void => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (license: License): void => {
    state.user_id = license.user_id ? String(license.user_id) : '';
    state.code = license.code;
    state.is_active = license.is_active;
    state.used_at = toDateTimeLocal(license.used_at);
    state.expires_at = toDateTimeLocal(license.expires_at);
    editingLicenseId.value = license.id;
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

const buildPayload = (): LicensePayload => ({
    user_id: state.user_id.trim() === '' ? null : Number(state.user_id),
    code: state.code,
    is_active: state.is_active,
    used_at: state.used_at || null,
    expires_at: state.expires_at || null,
});

const storeLicense = async (): Promise<License> => {
    const response = await axios.post<ResourceResponse<License>>(
        '/api/licenses',
        buildPayload(),
    );

    return response.data.data;
};

const updateLicense = async (id: number): Promise<License> => {
    const response = await axios.patch<ResourceResponse<License>>(
        `/api/licenses/${id}`,
        buildPayload(),
    );

    return response.data.data;
};

const openDeleteModal = (license: License): void => {
    deletingLicense.value = license;
    deleteMessage.value = null;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = (): void => {
    if (isDeleting.value) {
        return;
    }

    isDeleteModalOpen.value = false;
    deletingLicense.value = null;
    deleteMessage.value = null;
};

const deleteLicense = async (): Promise<void> => {
    if (!deletingLicense.value) {
        return;
    }

    isDeleting.value = true;
    deleteMessage.value = null;

    try {
        await axios.delete(`/api/licenses/${deletingLicense.value.id}`);

        const targetPage =
            licenseData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;

        isDeleteModalOpen.value = false;
        deletingLicense.value = null;

        if (targetPage !== currentPage.value) {
            currentPage.value = targetPage;
        } else {
            await fetchLicenses(targetPage);
        }
    } catch {
        deleteMessage.value = 'License gagal dihapus.';
    } finally {
        isDeleting.value = false;
    }
};

const handleValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        formMessage.value = 'License gagal disimpan.';

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

const submitLicense = async (
    _event: FormSubmitEvent<LicenseFormState>,
): Promise<void> => {
    isSaving.value = true;
    formMessage.value = null;
    serverErrors.value = {};

    try {
        if (editingLicenseId.value) {
            await updateLicense(editingLicenseId.value);
        } else {
            await storeLicense();
        }

        isModalOpen.value = false;
        resetForm();
        await fetchLicenses(currentPage.value);
    } catch (error) {
        handleValidationErrors(error);
    } finally {
        isSaving.value = false;
    }
};

watch(currentPage, (page) => {
    if (page !== meta.value?.current_page) {
        void fetchLicenses(page);
    }
});

watch(isModalOpen, (open) => {
    if (!open && !isSaving.value) {
        resetForm();
    }
});

watch(isDeleteModalOpen, (open) => {
    if (!open && !isDeleting.value) {
        deletingLicense.value = null;
        deleteMessage.value = null;
    }
});

onMounted(() => {
    void fetchLicenses();
});
</script>

<template>
    <Head title="Licences" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">
                    Licences
                </h1>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Search licences..."
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
                    aria-label="Refresh licences"
                    @click="fetchLicenses(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Gagal memuat license"
            :description="errorMessage"
            :actions="[
                {
                    label: 'Coba lagi',
                    icon: 'i-lucide-refresh-cw',
                    color: 'error',
                    variant: 'subtle',
                    onClick: () => fetchLicenses(currentPage),
                },
            ]"
        />

        <div class="overflow-hidden rounded-lg border border-default bg-default">
            <UTable
                :data="filteredLicenses"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <template #code-cell="{ row }">
                    <div class="flex items-center gap-3">
                        <UAvatar
                            :alt="row.original.code"
                            icon="i-lucide-key-round"
                            size="lg"
                        />

                        <div class="min-w-0">
                            <p class="truncate font-medium text-highlighted">
                                {{ row.original.code }}
                            </p>
                            <p class="text-xs text-muted">
                                ID {{ row.original.id }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #user-cell="{ row }">
                    <div v-if="row.original.user" class="min-w-0">
                        <p class="truncate text-sm font-medium text-highlighted">
                            {{ row.original.user.name }}
                        </p>
                        <p class="truncate text-xs text-muted">
                            {{ row.original.user.email }}
                        </p>
                    </div>
                    <span v-else class="text-sm text-muted">Unassigned</span>
                </template>

                <template #is_active-cell="{ row }">
                    <UBadge
                        :color="row.original.is_active ? 'success' : 'neutral'"
                        variant="subtle"
                        :label="row.original.is_active ? 'Active' : 'Inactive'"
                    />
                </template>

                <template #used_at-cell="{ row }">
                    {{ formatDateTime(row.original.used_at) }}
                </template>

                <template #expires_at-cell="{ row }">
                    {{ formatDateTime(row.original.expires_at) }}
                </template>

                <template #created_at-cell="{ row }">
                    {{ formatDateTime(row.original.created_at) }}
                </template>

                <template #actions-cell="{ row }">
                    <div class="flex justify-end gap-1">
                        <UButton
                            icon="i-lucide-pencil"
                            color="neutral"
                            variant="ghost"
                            aria-label="Edit license"
                            :disabled="isLoading || isDeleting"
                            @click="openEditModal(row.original)"
                        />

                        <UButton
                            icon="i-lucide-trash"
                            color="error"
                            variant="ghost"
                            aria-label="Delete license"
                            :disabled="isLoading || isDeleting"
                            @click="openDeleteModal(row.original)"
                        />
                    </div>
                </template>

                <template #empty>
                    <div class="flex flex-col items-center gap-2 py-10">
                        <UIcon name="i-lucide-inbox" class="size-8 text-muted" />
                        <p class="font-medium text-highlighted">
                            Tidak ada license
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
                    id="license-form"
                    :state="state"
                    :validate="validate"
                    class="space-y-4"
                    @submit="submitLicense"
                >
                    <UFormField
                        name="code"
                        label="Code"
                        required
                        :error="fieldError('code')"
                    >
                        <div class="flex gap-2">
                            <UInput
                                v-model="state.code"
                                placeholder="A1B2C3D4E5F6"
                                :disabled="isSaving"
                                class="min-w-0 flex-1"
                            />

                            <UButton
                                type="button"
                                icon="i-lucide-sparkles"
                                label="Generate"
                                color="neutral"
                                variant="outline"
                                :disabled="isSaving"
                                @click="generateCode"
                            />
                        </div>
                    </UFormField>

                    <UFormField
                        name="user_id"
                        label="User ID"
                        hint="Optional"
                        :error="fieldError('user_id')"
                    >
                        <UInput
                            v-model="state.user_id"
                            type="number"
                            min="1"
                            placeholder="Leave empty if unassigned"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="is_active"
                        label="Active"
                        :error="fieldError('is_active')"
                    >
                        <USwitch
                            v-model="state.is_active"
                            :disabled="isSaving"
                        />
                    </UFormField>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <UFormField
                            name="used_at"
                            label="Used At"
                            hint="Optional"
                            :error="fieldError('used_at')"
                        >
                            <UInput
                                v-model="state.used_at"
                                type="datetime-local"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            name="expires_at"
                            label="Expires At"
                            hint="Optional"
                            :error="fieldError('expires_at')"
                        >
                            <UInput
                                v-model="state.expires_at"
                                type="datetime-local"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>
                    </div>
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
                    form="license-form"
                    icon="i-lucide-save"
                    :label="submitLabel"
                    :loading="isSaving"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isDeleteModalOpen"
            title="Delete License"
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
                    @click="deleteLicense"
                />
            </template>
        </UModal>
    </div>
</template>
