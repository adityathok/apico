<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { FormError, FormSubmitEvent, TableColumn } from '@nuxt/ui';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { websites } from '@/routes';

type WebsiteStatus = 'active' | 'invalid';

type Website = {
    id: number;
    domain: string;
    ip_address: string | null;
    license_key: string;
    status: WebsiteStatus;
    theme_version: string | null;
    plugin_version: string | null;
    wp_version: string | null;
    php_version: string | null;
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

type WebsitesResponse = {
    data: Website[];
    meta: PaginationMeta;
};

type ResourceResponse<T> = {
    data: T;
};

type WebsiteFormState = {
    domain: string;
    ip_address: string;
    license_key: string;
    status: WebsiteStatus;
    theme_version: string;
    plugin_version: string;
    wp_version: string;
    php_version: string;
};

type WebsitePayload = {
    domain: string;
    ip_address: string | null;
    license_key: string;
    status: WebsiteStatus;
    theme_version: string | null;
    plugin_version: string | null;
    wp_version: string | null;
    php_version: string | null;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Websites',
                href: websites(),
            },
        ],
    },
});

const columns: TableColumn<Website>[] = [
    {
        accessorKey: 'domain',
        header: 'Website',
    },
    {
        accessorKey: 'status',
        header: 'Status',
    },
    {
        accessorKey: 'license_key',
        header: 'License',
    },
    {
        accessorKey: 'ip_address',
        header: 'IP Address',
    },
    {
        accessorKey: 'plugin_version',
        header: 'Plugin',
    },
    {
        accessorKey: 'wp_version',
        header: 'WordPress',
    },
    {
        accessorKey: 'php_version',
        header: 'PHP',
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
    },
    {
        id: 'actions',
    },
];

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Invalid', value: 'invalid' },
];

const state = reactive<WebsiteFormState>({
    domain: '',
    ip_address: '',
    license_key: '',
    status: 'active',
    theme_version: '',
    plugin_version: '',
    wp_version: '',
    php_version: '',
});

const websiteData = ref<Website[]>([]);
const meta = ref<PaginationMeta | null>(null);
const search = ref('');
const currentPage = ref(1);
const isLoading = ref(true);
const isSaving = ref(false);
const isDeleting = ref(false);
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingWebsiteId = ref<number | null>(null);
const deletingWebsite = ref<Website | null>(null);
const errorMessage = ref<string | null>(null);
const formMessage = ref<string | null>(null);
const deleteMessage = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});

const isEditing = computed(() => editingWebsiteId.value !== null);
const modalTitle = computed(() =>
    isEditing.value ? 'Edit Website' : 'Create Website',
);
const modalDescription = computed(() =>
    isEditing.value
        ? 'Update detail website yang sudah ada.'
        : 'Tambahkan website baru yang memakai license.',
);
const submitLabel = computed(() =>
    isEditing.value ? 'Update Website' : 'Create Website',
);
const deleteModalDescription = computed(() => {
    if (!deletingWebsite.value) {
        return 'Website ini akan dihapus secara permanen.';
    }

    return `Website "${deletingWebsite.value.domain}" akan dihapus secara permanen.`;
});

const filteredWebsites = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return websiteData.value;
    }

    return websiteData.value.filter((website) => {
        const searchableContent = [
            website.domain,
            website.ip_address,
            website.license_key,
            website.status,
            website.theme_version,
            website.plugin_version,
            website.wp_version,
            website.php_version,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return searchableContent.includes(query);
    });
});

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) {
        return '0 websites';
    }

    return `${meta.value.from}-${meta.value.to} of ${meta.value.total} websites`;
});

const fetchWebsites = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.get<WebsitesResponse>('/ajax/websites', {
            params: { page },
        });

        websiteData.value = response.data.data;
        meta.value = response.data.meta;
        currentPage.value = response.data.meta.current_page;
    } catch {
        errorMessage.value = 'Data website gagal dimuat.';
    } finally {
        isLoading.value = false;
    }
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
    }).format(new Date(value));
};

const validate = (formState: Partial<WebsiteFormState>): FormError[] => {
    const errors: FormError[] = [];

    if (!formState.domain?.trim()) {
        errors.push({ name: 'domain', message: 'Domain wajib diisi.' });
    }

    if (!formState.license_key?.trim()) {
        errors.push({
            name: 'license_key',
            message: 'License key wajib diisi.',
        });
    }

    if (formState.status && !['active', 'invalid'].includes(formState.status)) {
        errors.push({ name: 'status', message: 'Status tidak valid.' });
    }

    return errors;
};

const fieldError = (name: string): string | undefined => {
    return serverErrors.value[name];
};

const resetForm = (): void => {
    state.domain = '';
    state.ip_address = '';
    state.license_key = '';
    state.status = 'active';
    state.theme_version = '';
    state.plugin_version = '';
    state.wp_version = '';
    state.php_version = '';
    editingWebsiteId.value = null;
    formMessage.value = null;
    serverErrors.value = {};
};

const openCreateModal = (): void => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (website: Website): void => {
    state.domain = website.domain;
    state.ip_address = website.ip_address ?? '';
    state.license_key = website.license_key;
    state.status = website.status;
    state.theme_version = website.theme_version ?? '';
    state.plugin_version = website.plugin_version ?? '';
    state.wp_version = website.wp_version ?? '';
    state.php_version = website.php_version ?? '';
    editingWebsiteId.value = website.id;
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

const buildPayload = (): WebsitePayload => ({
    domain: state.domain.trim(),
    ip_address: nullableTrimmed(state.ip_address),
    license_key: state.license_key.trim(),
    status: state.status,
    theme_version: nullableTrimmed(state.theme_version),
    plugin_version: nullableTrimmed(state.plugin_version),
    wp_version: nullableTrimmed(state.wp_version),
    php_version: nullableTrimmed(state.php_version),
});

const storeWebsite = async (): Promise<Website> => {
    const response = await axios.post<ResourceResponse<Website>>(
        '/ajax/websites',
        buildPayload(),
    );

    return response.data.data;
};

const updateWebsite = async (id: number): Promise<Website> => {
    const response = await axios.patch<ResourceResponse<Website>>(
        `/ajax/websites/${id}`,
        buildPayload(),
    );

    return response.data.data;
};

const openDeleteModal = (website: Website): void => {
    deletingWebsite.value = website;
    deleteMessage.value = null;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = (): void => {
    if (isDeleting.value) {
        return;
    }

    isDeleteModalOpen.value = false;
    deletingWebsite.value = null;
    deleteMessage.value = null;
};

const deleteWebsite = async (): Promise<void> => {
    if (!deletingWebsite.value) {
        return;
    }

    isDeleting.value = true;
    deleteMessage.value = null;

    try {
        await axios.delete(`/ajax/websites/${deletingWebsite.value.id}`);

        const targetPage =
            websiteData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;

        isDeleteModalOpen.value = false;
        deletingWebsite.value = null;

        if (targetPage !== currentPage.value) {
            currentPage.value = targetPage;
        } else {
            await fetchWebsites(targetPage);
        }
    } catch {
        deleteMessage.value = 'Website gagal dihapus.';
    } finally {
        isDeleting.value = false;
    }
};

const handleValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        formMessage.value = 'Website gagal disimpan.';

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

const submitWebsite = async (
    _event: FormSubmitEvent<WebsiteFormState>,
): Promise<void> => {
    isSaving.value = true;
    formMessage.value = null;
    serverErrors.value = {};

    try {
        if (editingWebsiteId.value) {
            await updateWebsite(editingWebsiteId.value);
        } else {
            await storeWebsite();
        }

        isModalOpen.value = false;
        resetForm();
        await fetchWebsites(currentPage.value);
    } catch (error) {
        handleValidationErrors(error);
    } finally {
        isSaving.value = false;
    }
};

watch(currentPage, (page) => {
    if (page !== meta.value?.current_page) {
        void fetchWebsites(page);
    }
});

watch(isModalOpen, (open) => {
    if (!open && !isSaving.value) {
        resetForm();
    }
});

watch(isDeleteModalOpen, (open) => {
    if (!open && !isDeleting.value) {
        deletingWebsite.value = null;
        deleteMessage.value = null;
    }
});

onMounted(() => {
    void fetchWebsites();
});
</script>

<template>
    <Head title="Websites" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">
                    Websites
                </h1>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Search websites..."
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
                    aria-label="Refresh websites"
                    @click="fetchWebsites(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Gagal memuat website"
            :description="errorMessage"
            :actions="[
                {
                    label: 'Coba lagi',
                    icon: 'i-lucide-refresh-cw',
                    color: 'error',
                    variant: 'subtle',
                    onClick: () => fetchWebsites(currentPage),
                },
            ]"
        />

        <div
            class="overflow-hidden rounded-lg border border-default bg-default"
        >
            <UTable
                :data="filteredWebsites"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <template #domain-cell="{ row }">
                    <div class="flex items-center gap-3">
                        <UAvatar
                            :alt="row.original.domain"
                            icon="i-lucide-globe"
                            size="lg"
                        />

                        <div class="min-w-0">
                            <p class="truncate font-medium text-highlighted">
                                {{ row.original.domain }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #status-cell="{ row }">
                    <UBadge
                        :color="
                            row.original.status === 'active'
                                ? 'success'
                                : 'error'
                        "
                        variant="subtle"
                        :label="
                            row.original.status === 'active'
                                ? 'Active'
                                : 'Invalid'
                        "
                    />
                </template>

                <template #license_key-cell="{ row }">
                    <UBadge
                        color="neutral"
                        variant="subtle"
                        :label="row.original.license_key"
                    />
                </template>

                <template #ip_address-cell="{ row }">
                    <span class="text-sm text-muted">
                        {{ row.original.ip_address || '-' }}
                    </span>
                </template>

                <template #plugin_version-cell="{ row }">
                    <span class="text-sm">
                        {{ row.original.plugin_version || '-' }}
                    </span>
                </template>

                <template #wp_version-cell="{ row }">
                    <span class="text-sm">
                        {{ row.original.wp_version || '-' }}
                    </span>
                </template>

                <template #php_version-cell="{ row }">
                    <span class="text-sm">
                        {{ row.original.php_version || '-' }}
                    </span>
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
                            aria-label="Edit website"
                            :disabled="isLoading || isDeleting"
                            @click="openEditModal(row.original)"
                        />

                        <UButton
                            icon="i-lucide-trash"
                            color="error"
                            variant="ghost"
                            aria-label="Delete website"
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
                            Tidak ada website
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
                    id="website-form"
                    :state="state"
                    :validate="validate"
                    class="space-y-4"
                    @submit="submitWebsite"
                >
                    <UFormField
                        name="domain"
                        label="Domain"
                        required
                        :error="fieldError('domain')"
                    >
                        <UInput
                            v-model="state.domain"
                            placeholder="example.com"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="license_key"
                        label="License Key"
                        required
                        :error="fieldError('license_key')"
                    >
                        <UInput
                            v-model="state.license_key"
                            placeholder="APICO-2026-0001"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <UFormField
                            name="status"
                            label="Status"
                            required
                            :error="fieldError('status')"
                        >
                            <USelect
                                v-model="state.status"
                                :items="statusOptions"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            name="ip_address"
                            label="IP Address"
                            hint="Optional"
                            :error="fieldError('ip_address')"
                        >
                            <UInput
                                v-model="state.ip_address"
                                placeholder="192.168.10.10"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <UFormField
                            name="theme_version"
                            label="Theme Version"
                            hint="Optional"
                            :error="fieldError('theme_version')"
                        >
                            <UInput
                                v-model="state.theme_version"
                                placeholder="1.0.0"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            name="plugin_version"
                            label="Plugin Version"
                            hint="Optional"
                            :error="fieldError('plugin_version')"
                        >
                            <UInput
                                v-model="state.plugin_version"
                                placeholder="1.0.0"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <UFormField
                            name="wp_version"
                            label="WordPress Version"
                            hint="Optional"
                            :error="fieldError('wp_version')"
                        >
                            <UInput
                                v-model="state.wp_version"
                                placeholder="6.5.4"
                                :disabled="isSaving"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            name="php_version"
                            label="PHP Version"
                            hint="Optional"
                            :error="fieldError('php_version')"
                        >
                            <UInput
                                v-model="state.php_version"
                                placeholder="8.3"
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
                    form="website-form"
                    icon="i-lucide-save"
                    :label="submitLabel"
                    :loading="isSaving"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isDeleteModalOpen"
            title="Delete Website"
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
                    @click="deleteWebsite"
                />
            </template>
        </UModal>
    </div>
</template>
