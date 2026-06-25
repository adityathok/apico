<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

type UpdateInfo = {
    has_update: boolean;
    current_version: string;
    latest_version: string;
    release_notes: string;
    download_url: string | null;
    published_at: string | null;
};

const currentVersion = ref('');
const updateInfo = ref<UpdateInfo | null>(null);
const isChecking = ref(false);
const isUpdating = ref(false);
const isRestoring = ref(false);
const message = ref('');
const errorMessage = ref('');

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const checkForUpdates = async () => {
    isChecking.value = true;
    errorMessage.value = '';
    message.value = '';

    try {
        const response = await fetch('/admin/system/check-updates');
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Gagal mengecek update');
        }

        updateInfo.value = data;
        currentVersion.value = data.current_version;
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Gagal mengecek update';
    } finally {
        isChecking.value = false;
    }
};

const performUpdate = async () => {
    if (!updateInfo.value?.download_url) {
        return;
    }

    isUpdating.value = true;
    errorMessage.value = '';
    message.value = 'Memulai update...';

    try {
        const response = await fetch('/admin/system/perform-update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                download_url: updateInfo.value.download_url,
                version: updateInfo.value.latest_version,
            }),
        });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Gagal install update');
        }

        message.value = 'Update berhasil. Memuat ulang halaman...';
        setTimeout(() => window.location.reload(), 1500);
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Gagal install update';
        message.value = '';
    } finally {
        isUpdating.value = false;
    }
};

const restoreBackup = async () => {
    if (!confirm('Restore backup terakhir? Perubahan file saat ini akan diganti.')) {
        return;
    }

    isRestoring.value = true;
    errorMessage.value = '';
    message.value = '';

    try {
        const response = await fetch('/admin/system/restore-backup', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() },
        });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error || 'Gagal restore backup');
        }

        message.value = 'Backup berhasil direstore. Memuat ulang halaman...';
        setTimeout(() => window.location.reload(), 1500);
    } catch (error) {
        errorMessage.value = error instanceof Error ? error.message : 'Gagal restore backup';
    } finally {
        isRestoring.value = false;
    }
};

const formatDate = (date: string | null) => {
    if (!date) {
        return '-';
    }

    return new Date(date).toLocaleString('id-ID');
};

onMounted(checkForUpdates);
</script>

<template>
    <Head title="System Update" />

    <AppLayout>
        <main class="mx-auto w-full max-w-4xl space-y-6 p-6">
            <section class="rounded-xl border bg-white p-6 shadow-sm dark:bg-neutral-950">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold">Update Sistem</h1>
                        <p class="text-sm text-muted-foreground">Update dari GitHub Releases Velocity-Developer/api-vd-co.</p>
                    </div>
                    <button class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50" :disabled="isChecking" @click="checkForUpdates">
                        {{ isChecking ? 'Mengecek...' : 'Cek Update' }}
                    </button>
                </div>

                <div class="mt-6 rounded-lg border p-4">
                    <p class="text-sm text-muted-foreground">Versi saat ini</p>
                    <p class="font-mono text-lg">{{ currentVersion || 'Memuat...' }}</p>
                </div>

                <div v-if="updateInfo" class="mt-4 rounded-lg border p-4" :class="updateInfo.has_update ? 'border-green-300 bg-green-50 dark:bg-green-950/20' : 'bg-muted/40'">
                    <h2 class="font-semibold">{{ updateInfo.has_update ? 'Update tersedia' : 'Sudah versi terbaru' }}</h2>
                    <p class="mt-1 text-sm">Versi terbaru: <span class="font-mono">{{ updateInfo.latest_version }}</span></p>
                    <p class="text-sm">Rilis: {{ formatDate(updateInfo.published_at) }}</p>
                    <pre v-if="updateInfo.release_notes" class="mt-3 whitespace-pre-wrap rounded-md bg-background p-3 text-sm">{{ updateInfo.release_notes }}</pre>
                    <button
                        v-if="updateInfo.has_update"
                        class="mt-4 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        :disabled="isUpdating || !updateInfo.download_url"
                        @click="performUpdate"
                    >
                        {{ isUpdating ? 'Menginstall...' : 'Install Update' }}
                    </button>
                </div>

                <div class="mt-6 border-t pt-6">
                    <h2 class="font-semibold">Backup & Restore</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Backup otomatis dibuat sebelum update.</p>
                    <button class="mt-3 rounded-md border px-4 py-2 text-sm font-medium disabled:opacity-50" :disabled="isRestoring" @click="restoreBackup">
                        {{ isRestoring ? 'Restore...' : 'Restore Backup Terakhir' }}
                    </button>
                </div>

                <p v-if="message" class="mt-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">{{ message }}</p>
                <p v-if="errorMessage" class="mt-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ errorMessage }}</p>
            </section>
        </main>
    </AppLayout>
</template>
