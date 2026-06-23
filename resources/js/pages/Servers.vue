<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { FormError, FormSubmitEvent, TableColumn } from '@nuxt/ui';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { servers as serversRoute } from '@/routes';

type ServerItem = {
    id: number;
    server_ip: string;
    server_domain: string;
    server_name: string;
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

type ServersResponse = {
    data: ServerItem[];
    meta: PaginationMeta;
};

type ResourceResponse<T> = {
    data: T;
};

type ServerFormState = {
    server_ip: string;
    server_domain: string;
    server_name: string;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Servers',
                href: serversRoute(),
            },
        ],
    },
});

const columns: TableColumn<ServerItem>[] = [
    {
        accessorKey: 'server_name',
        header: 'Server',
    },
    {
        accessorKey: 'server_domain',
        header: 'Domain',
    },
    {
        accessorKey: 'server_ip',
        header: 'IP Address',
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
    },
    {
        id: 'actions',
    },
];

const state = reactive<ServerFormState>({
    server_ip: '',
    server_domain: '',
    server_name: '',
});

const serverData = ref<ServerItem[]>([]);
const meta = ref<PaginationMeta | null>(null);
const search = ref('');
const currentPage = ref(1);
const isLoading = ref(true);
const isSaving = ref(false);
const isDeleting = ref(false);
const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingServerId = ref<number | null>(null);
const deletingServer = ref<ServerItem | null>(null);
const errorMessage = ref<string | null>(null);
const formMessage = ref<string | null>(null);
const deleteMessage = ref<string | null>(null);
const serverErrors = ref<Record<string, string>>({});

const isEditing = computed(() => editingServerId.value !== null);
const modalTitle = computed(() =>
    isEditing.value ? 'Edit Server' : 'Create Server',
);
const modalDescription = computed(() =>
    isEditing.value
        ? 'Perbarui detail server yang sudah ada.'
        : 'Tambahkan server baru untuk kebutuhan infrastruktur.',
);
const submitLabel = computed(() =>
    isEditing.value ? 'Update Server' : 'Create Server',
);
const deleteModalDescription = computed(() => {
    if (!deletingServer.value) {
        return 'Server ini akan dihapus secara permanen.';
    }

    return `Server "${deletingServer.value.server_name}" akan dihapus secara permanen.`;
});

const filteredServers = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return serverData.value;
    }

    return serverData.value.filter((server) => {
        const searchableContent = [
            server.server_name,
            server.server_domain,
            server.server_ip,
        ]
            .join(' ')
            .toLowerCase();

        return searchableContent.includes(query);
    });
});

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) {
        return '0 servers';
    }

    return `${meta.value.from}-${meta.value.to} of ${meta.value.total} servers`;
});

const fetchServers = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.get<ServersResponse>('/ajax/servers', {
            params: { page },
        });

        serverData.value = response.data.data;
        meta.value = response.data.meta;
        currentPage.value = response.data.meta.current_page;
    } catch {
        errorMessage.value = 'Data server gagal dimuat.';
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

const validate = (formState: Partial<ServerFormState>): FormError[] => {
    const errors: FormError[] = [];

    if (!formState.server_name?.trim()) {
        errors.push({
            name: 'server_name',
            message: 'Nama server wajib diisi.',
        });
    }

    if (!formState.server_domain?.trim()) {
        errors.push({
            name: 'server_domain',
            message: 'Domain server wajib diisi.',
        });
    }

    if (!formState.server_ip?.trim()) {
        errors.push({
            name: 'server_ip',
            message: 'IP server wajib diisi.',
        });
    }

    return errors;
};

const fieldError = (name: string): string | undefined => {
    return serverErrors.value[name];
};

const resetForm = (): void => {
    state.server_ip = '';
    state.server_domain = '';
    state.server_name = '';
    editingServerId.value = null;
    formMessage.value = null;
    serverErrors.value = {};
};

const openCreateModal = (): void => {
    resetForm();
    isModalOpen.value = true;
};

const openEditModal = (server: ServerItem): void => {
    state.server_ip = server.server_ip;
    state.server_domain = server.server_domain;
    state.server_name = server.server_name;
    editingServerId.value = server.id;
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

const buildPayload = (): ServerFormState => ({
    server_ip: state.server_ip.trim(),
    server_domain: state.server_domain.trim(),
    server_name: state.server_name.trim(),
});

const storeServer = async (): Promise<ServerItem> => {
    const response = await axios.post<ResourceResponse<ServerItem>>(
        '/ajax/servers',
        buildPayload(),
    );

    return response.data.data;
};

const updateServer = async (id: number): Promise<ServerItem> => {
    const response = await axios.patch<ResourceResponse<ServerItem>>(
        `/ajax/servers/${id}`,
        buildPayload(),
    );

    return response.data.data;
};

const openDeleteModal = (server: ServerItem): void => {
    deletingServer.value = server;
    deleteMessage.value = null;
    isDeleteModalOpen.value = true;
};

const closeDeleteModal = (): void => {
    if (isDeleting.value) {
        return;
    }

    isDeleteModalOpen.value = false;
    deletingServer.value = null;
    deleteMessage.value = null;
};

const deleteServer = async (): Promise<void> => {
    if (!deletingServer.value) {
        return;
    }

    isDeleting.value = true;
    deleteMessage.value = null;

    try {
        await axios.delete(`/ajax/servers/${deletingServer.value.id}`);

        const targetPage =
            serverData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;

        isDeleteModalOpen.value = false;
        deletingServer.value = null;

        if (targetPage !== currentPage.value) {
            currentPage.value = targetPage;
        } else {
            await fetchServers(targetPage);
        }
    } catch {
        deleteMessage.value = 'Server gagal dihapus.';
    } finally {
        isDeleting.value = false;
    }
};

const handleValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        formMessage.value = 'Server gagal disimpan.';

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

const submitServer = async (
    _event: FormSubmitEvent<ServerFormState>,
): Promise<void> => {
    isSaving.value = true;
    formMessage.value = null;
    serverErrors.value = {};

    try {
        if (editingServerId.value) {
            await updateServer(editingServerId.value);
        } else {
            await storeServer();
        }

        isModalOpen.value = false;
        resetForm();
        await fetchServers(currentPage.value);
    } catch (error) {
        handleValidationErrors(error);
    } finally {
        isSaving.value = false;
    }
};

watch(currentPage, (page) => {
    if (page !== meta.value?.current_page) {
        void fetchServers(page);
    }
});

watch(isModalOpen, (open) => {
    if (!open && !isSaving.value) {
        resetForm();
    }
});

watch(isDeleteModalOpen, (open) => {
    if (!open && !isDeleting.value) {
        deletingServer.value = null;
        deleteMessage.value = null;
    }
});

onMounted(() => {
    void fetchServers();
});
</script>

<template>
    <Head title="Servers" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">Servers</h1>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Search servers..."
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
                    aria-label="Refresh servers"
                    @click="fetchServers(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Gagal memuat server"
            :description="errorMessage"
            :actions="[
                {
                    label: 'Coba lagi',
                    icon: 'i-lucide-refresh-cw',
                    color: 'error',
                    variant: 'subtle',
                    onClick: () => fetchServers(currentPage),
                },
            ]"
        />

        <div
            class="overflow-hidden rounded-lg border border-default bg-default"
        >
            <UTable
                :data="filteredServers"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <template #server_name-cell="{ row }">
                    <div class="flex items-center gap-3">
                        <UAvatar
                            :alt="row.original.server_name"
                            icon="i-lucide-server"
                            size="lg"
                        />

                        <div class="min-w-0">
                            <p class="truncate font-medium text-highlighted">
                                {{ row.original.server_name }}
                            </p>
                            <p class="text-xs text-muted">
                                {{ row.original.server_domain }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #server_domain-cell="{ row }">
                    <UBadge
                        color="neutral"
                        variant="subtle"
                        :label="row.original.server_domain"
                    />
                </template>

                <template #server_ip-cell="{ row }">
                    <span class="font-mono text-sm text-muted">
                        {{ row.original.server_ip }}
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
                            aria-label="Edit server"
                            :disabled="isLoading || isDeleting"
                            @click="openEditModal(row.original)"
                        />

                        <UButton
                            icon="i-lucide-trash"
                            color="error"
                            variant="ghost"
                            aria-label="Delete server"
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
                            Tidak ada server
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
                    id="server-form"
                    :state="state"
                    :validate="validate"
                    class="space-y-4"
                    @submit="submitServer"
                >
                    <UFormField
                        name="server_name"
                        label="Server Name"
                        required
                        :error="fieldError('server_name')"
                    >
                        <UInput
                            v-model="state.server_name"
                            placeholder="Primary API Server"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="server_domain"
                        label="Server Domain"
                        required
                        :error="fieldError('server_domain')"
                    >
                        <UInput
                            v-model="state.server_domain"
                            placeholder="api.example.test"
                            :disabled="isSaving"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="server_ip"
                        label="Server IP"
                        required
                        :error="fieldError('server_ip')"
                    >
                        <UInput
                            v-model="state.server_ip"
                            placeholder="192.168.10.20"
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
                    form="server-form"
                    icon="i-lucide-save"
                    :label="submitLabel"
                    :loading="isSaving"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="isDeleteModalOpen"
            title="Delete Server"
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
                    @click="deleteServer"
                />
            </template>
        </UModal>
    </div>
</template>
