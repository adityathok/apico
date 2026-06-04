<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { TableColumn } from '@nuxt/ui';
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { post, posts, read } from '@/routes';

type User = {
    id: number;
    name: string;
    email: string;
};

type Taxonomy = {
    id: number;
    name: string;
    slug: string;
};

type Post = {
    id: number;
    user_id: number;
    title: string;
    slug: string;
    image: string | null;
    image_caption: string | null;
    excerpt: string | null;
    content: string;
    published_at: string | null;
    created_at: string;
    updated_at: string;
    user?: User;
    categories?: Taxonomy[];
    tags?: Taxonomy[];
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

type PostsResponse = {
    data: Post[];
    meta: PaginationMeta;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Posts',
                href: posts(),
            },
        ],
    },
});

const columns: TableColumn<Post>[] = [
    {
        accessorKey: 'title',
        header: 'Post',
    },
    {
        id: 'author',
        header: 'Author',
    },
    {
        id: 'categories',
        header: 'Categories',
    },
    {
        id: 'status',
        header: 'Status',
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
    },
    {
        id: 'actions',
        header: '',
    },
];

const postsData = ref<Post[]>([]);
const meta = ref<PaginationMeta | null>(null);
const search = ref('');
const currentPage = ref(1);
const isLoading = ref(true);
const deletingPostId = ref<number | null>(null);
const errorMessage = ref<string | null>(null);

const filteredPosts = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return postsData.value;
    }

    return postsData.value.filter((post) => {
        const searchableContent = [
            post.title,
            post.image_caption,
            post.excerpt,
            post.slug,
            post.user?.name,
            ...(post.categories ?? []).map((category) => category.name),
            ...(post.tags ?? []).map((tag) => tag.name),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return searchableContent.includes(query);
    });
});

const paginationSummary = computed(() => {
    if (!meta.value || meta.value.total === 0) {
        return '0 posts';
    }

    return `${meta.value.from}-${meta.value.to} of ${meta.value.total} posts`;
});

const fetchPosts = async (page = 1): Promise<void> => {
    isLoading.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.get<PostsResponse>('/api/posts', {
            params: { page },
        });

        postsData.value = response.data.data;
        meta.value = response.data.meta;
        currentPage.value = response.data.meta.current_page;
    } catch {
        errorMessage.value = 'Data post gagal dimuat.';
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

const imageUrl = (path: string | null): string | undefined => {
    if (!path) {
        return undefined;
    }

    if (path.startsWith('http')) {
        return path;
    }

    return `/storage/${path}`;
};

const editUrl = (id: number): string => {
    return post.url({ query: { id } });
};

const previewUrl = (slug: string): string => {
    return read(slug).url;
};

const deletePost = async (postToDelete: Post): Promise<void> => {
    const shouldDelete = window.confirm(`Hapus post "${postToDelete.title}"?`);

    if (!shouldDelete) {
        return;
    }

    deletingPostId.value = postToDelete.id;
    errorMessage.value = null;

    try {
        await axios.delete(`/api/posts/${postToDelete.id}`);

        const nextPage =
            postsData.value.length === 1 && currentPage.value > 1
                ? currentPage.value - 1
                : currentPage.value;

        await fetchPosts(nextPage);
    } catch {
        errorMessage.value = 'Post gagal dihapus.';
    } finally {
        deletingPostId.value = null;
    }
};

watch(currentPage, (page) => {
    if (page !== meta.value?.current_page) {
        void fetchPosts(page);
    }
});

onMounted(() => {
    void fetchPosts();
});
</script>

<template>
    <Head title="Posts" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">Posts</h1>
                <p class="text-sm text-muted">
                    {{ paginationSummary }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <UButton
                    :to="post()"
                    icon="i-lucide-plus"
                    label="Add"
                />

                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    placeholder="Search posts..."
                    :disabled="isLoading"
                    class="w-full sm:w-64"
                />

                <UButton
                    icon="i-lucide-refresh-cw"
                    color="neutral"
                    variant="outline"
                    :loading="isLoading"
                    aria-label="Refresh posts"
                    @click="fetchPosts(currentPage)"
                />
            </div>
        </div>

        <UAlert
            v-if="errorMessage"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Gagal memuat post"
            :description="errorMessage"
            :actions="[
                {
                    label: 'Coba lagi',
                    icon: 'i-lucide-refresh-cw',
                    color: 'error',
                    variant: 'subtle',
                    onClick: () => fetchPosts(currentPage),
                },
            ]"
        />

        <div
            class="overflow-hidden rounded-lg border border-default bg-default"
        >
            <UTable
                :data="filteredPosts"
                :columns="columns"
                :loading="isLoading"
                sticky
            >
                <template #title-cell="{ row }">
                    <div class="flex items-center gap-3">
                        <UAvatar
                            :src="imageUrl(row.original.image)"
                            :alt="row.original.title"
                            icon="i-lucide-file-text"
                            size="lg"
                        />

                        <div class="min-w-0">
                            <p class="truncate font-medium text-highlighted">
                                {{ row.original.title }}
                            </p>
                            <p class="max-w-25 truncate text-xs text-muted">
                                {{ row.original.excerpt || row.original.slug }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #author-cell="{ row }">
                    <div>
                        <p class="font-medium text-highlighted">
                            {{ row.original.user?.name || 'Unknown' }}
                        </p>
                        <p class="text-xs text-muted">
                            {{ row.original.user?.email || '-' }}
                        </p>
                    </div>
                </template>

                <template #categories-cell="{ row }">
                    <div class="flex flex-wrap gap-1">
                        <UBadge
                            v-for="category in row.original.categories"
                            :key="category.id"
                            color="neutral"
                            variant="subtle"
                            :label="category.name"
                        />
                        <span
                            v-if="!row.original.categories?.length"
                            class="text-muted"
                        >
                            -
                        </span>
                    </div>
                </template>

                <template #status-cell="{ row }">
                    <UBadge
                        :color="
                            row.original.published_at ? 'success' : 'warning'
                        "
                        variant="subtle"
                        :label="
                            row.original.published_at ? 'Published' : 'Draft'
                        "
                    />
                </template>

                <template #created_at-cell="{ row }">
                    {{ formatDate(row.original.created_at) }}
                </template>

                <template #actions-cell="{ row }">
                    <div class="flex justify-end gap-1">
                        <UButton
                            :href="previewUrl(row.original.slug)"
                            target="_blank"
                            rel="noopener noreferrer"
                            icon="i-lucide-eye"
                            color="neutral"
                            variant="ghost"
                            aria-label="Preview post"
                        />

                        <UButton
                            :to="editUrl(row.original.id)"
                            icon="i-lucide-pencil"
                            color="neutral"
                            variant="ghost"
                            aria-label="Edit post"
                        />

                        <UButton
                            icon="i-lucide-trash-2"
                            color="error"
                            variant="ghost"
                            :loading="deletingPostId === row.original.id"
                            aria-label="Delete post"
                            @click="deletePost(row.original)"
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
                            Tidak ada post
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
    </div>
</template>
