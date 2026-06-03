<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import type { FormError, FormSubmitEvent } from '@nuxt/ui';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { post, posts } from '@/routes';

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
    excerpt: string | null;
    content: string;
    published_at: string | null;
    categories?: Taxonomy[];
    tags?: Taxonomy[];
};

type ResourceResponse<T> = {
    data: T;
};

type CollectionResponse<T> = {
    data: T[];
};

type SelectOption = {
    label: string;
    value: number;
};

type PostFormState = {
    title: string;
    slug: string;
    excerpt: string;
    content: string;
    published_at: string;
    category_ids: number[];
    tag_ids: number[];
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Posts',
                href: posts(),
            },
            {
                title: 'Post',
                href: post(),
            },
        ],
    },
});

const page = usePage();
const currentUserId = computed(() => Number(page.props.auth.user.id));

const state = reactive<PostFormState>({
    title: '',
    slug: '',
    excerpt: '',
    content: '',
    published_at: '',
    category_ids: [],
    tag_ids: [],
});

const categories = ref<SelectOption[]>([]);
const tags = ref<SelectOption[]>([]);
const image = ref<File | null>(null);
const currentImage = ref<string | null>(null);
const postId = ref<number | null>(null);
const isEdit = ref(false);
const isLoading = ref(true);
const isSaving = ref(false);
const serverErrors = ref<Record<string, string>>({});
const statusMessage = ref<string | null>(null);
const loadError = ref<string | null>(null);

const title = computed(() => (isEdit.value ? 'Edit Post' : 'Create Post'));
const submitLabel = computed(() =>
    isEdit.value ? 'Update Post' : 'Create Post',
);

const categoryItems = computed(() => categories.value);
const tagItems = computed(() => tags.value);

const imagePreview = computed(() => {
    if (image.value) {
        return URL.createObjectURL(image.value);
    }

    if (!currentImage.value) {
        return undefined;
    }

    if (currentImage.value.startsWith('http')) {
        return currentImage.value;
    }

    return `/storage/${currentImage.value}`;
});

const slugify = (value: string): string => {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
};

const formatDateTimeLocal = (value: string | null): string => {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const offset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
};

const validate = (formState: Partial<PostFormState>): FormError[] => {
    const errors: FormError[] = [];

    if (!formState.title?.trim()) {
        errors.push({ name: 'title', message: 'Title wajib diisi.' });
    }

    if (!formState.slug?.trim()) {
        errors.push({ name: 'slug', message: 'Slug wajib diisi.' });
    }

    if (!formState.content?.trim()) {
        errors.push({ name: 'content', message: 'Content wajib diisi.' });
    }

    return errors;
};

const fieldError = (name: string): string | undefined => {
    return serverErrors.value[name];
};

const resetForm = (): void => {
    state.title = '';
    state.slug = '';
    state.excerpt = '';
    state.content = '';
    state.published_at = '';
    state.category_ids = [];
    state.tag_ids = [];
    image.value = null;
    currentImage.value = null;
    postId.value = null;
    isEdit.value = false;
};

const fillForm = (postData: Post): void => {
    state.title = postData.title;
    state.slug = postData.slug;
    state.excerpt = postData.excerpt ?? '';
    state.content = postData.content;
    state.published_at = formatDateTimeLocal(postData.published_at);
    state.category_ids = (postData.categories ?? []).map(
        (category) => category.id,
    );
    state.tag_ids = (postData.tags ?? []).map((tag) => tag.id);
    currentImage.value = postData.image;
    postId.value = postData.id;
    isEdit.value = true;
};

const fetchTaxonomies = async (): Promise<void> => {
    const [categoryResponse, tagResponse] = await Promise.all([
        axios.get<CollectionResponse<Taxonomy>>('/api/categories'),
        axios.get<CollectionResponse<Taxonomy>>('/api/tags'),
    ]);

    categories.value = categoryResponse.data.data.map((category) => ({
        label: category.name,
        value: category.id,
    }));

    tags.value = tagResponse.data.data.map((tag) => ({
        label: tag.name,
        value: tag.id,
    }));
};

const fetchPost = async (id: number): Promise<void> => {
    try {
        const response = await axios.get<ResourceResponse<Post>>(
            `/api/posts/${id}`,
        );

        fillForm(response.data.data);
    } catch (error) {
        resetForm();

        if (error instanceof AxiosError && error.response?.status === 404) {
            statusMessage.value =
                'Post tidak ditemukan. Form dibuka dalam mode create.';

            return;
        }

        loadError.value =
            'Data post gagal dimuat. Form dibuka dalam mode create.';
    }
};

const buildPayload = (): Record<string, unknown> => ({
    user_id: currentUserId.value,
    title: state.title,
    slug: state.slug,
    excerpt: state.excerpt,
    content: state.content,
    published_at: state.published_at,
    category_ids: state.category_ids,
    tag_ids: state.tag_ids,
});

const buildFormData = (method?: 'PATCH'): FormData => {
    const formData = new FormData();

    if (method) {
        formData.append('_method', method);
    }

    formData.append('user_id', String(currentUserId.value));
    formData.append('title', state.title);
    formData.append('slug', state.slug);
    formData.append('excerpt', state.excerpt);
    formData.append('content', state.content);
    formData.append('published_at', state.published_at);

    state.category_ids.forEach((id) => {
        formData.append('category_ids[]', String(id));
    });

    state.tag_ids.forEach((id) => {
        formData.append('tag_ids[]', String(id));
    });

    if (image.value) {
        formData.append('image', image.value);
    }

    return formData;
};

const storePost = async (): Promise<Post> => {
    if (image.value) {
        const response = await axios.post<ResourceResponse<Post>>(
            '/api/posts',
            buildFormData(),
        );

        return response.data.data;
    }

    const response = await axios.post<ResourceResponse<Post>>(
        '/api/posts',
        buildPayload(),
    );

    return response.data.data;
};

const updatePost = async (id: number): Promise<Post> => {
    if (image.value) {
        await axios.post<ResourceResponse<Post>>(
            `/api/posts/${id}`,
            buildFormData('PATCH'),
        );

        const response = await axios.patch<ResourceResponse<Post>>(
            `/api/posts/${id}`,
            buildPayload(),
        );

        return response.data.data;
    }

    const response = await axios.patch<ResourceResponse<Post>>(
        `/api/posts/${id}`,
        buildPayload(),
    );

    return response.data.data;
};

const handleValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        loadError.value = 'Post gagal disimpan.';

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

const submit = async (
    _event: FormSubmitEvent<PostFormState>,
): Promise<void> => {
    isSaving.value = true;
    loadError.value = null;
    statusMessage.value = null;
    serverErrors.value = {};

    try {
        const editingPostId = isEdit.value ? postId.value : null;
        const savedPost = editingPostId
            ? await updatePost(editingPostId)
            : await storePost();

        fillForm(savedPost);
        image.value = null;
        statusMessage.value = editingPostId
            ? 'Post berhasil disimpan.'
            : 'Post berhasil dibuat.';

        window.history.replaceState(
            {},
            '',
            post.url({ query: { id: savedPost.id } }),
        );
    } catch (error) {
        handleValidationErrors(error);
    } finally {
        isSaving.value = false;
    }
};

const selectImage = (event: Event): void => {
    const target = event.target as HTMLInputElement;
    image.value = target.files?.[0] ?? null;
};

watch(
    () => state.title,
    (titleValue) => {
        if (!isEdit.value && state.slug === '') {
            state.slug = slugify(titleValue);
        }
    },
);

onMounted(async () => {
    isLoading.value = true;

    try {
        await fetchTaxonomies();

        const id = Number(
            new URLSearchParams(window.location.search).get('id'),
        );

        if (Number.isInteger(id) && id > 0) {
            await fetchPost(id);
        }
    } catch {
        loadError.value = 'Pilihan kategori atau tag gagal dimuat.';
    } finally {
        isLoading.value = false;
    }
});
</script>

<template>
    <Head :title="title" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-2xl font-semibold text-highlighted">
                    {{ title }}
                </h1>
                <p class="text-sm text-muted">
                    {{
                        isEdit
                            ? 'Update data post yang sudah ada.'
                            : 'Buat post baru.'
                    }}
                </p>
            </div>

            <UButton
                :to="posts()"
                icon="i-lucide-arrow-left"
                color="neutral"
                variant="outline"
                label="Back"
            />
        </div>

        <UAlert
            v-if="loadError"
            color="error"
            variant="soft"
            icon="i-lucide-circle-alert"
            title="Ada masalah"
            :description="loadError"
        />

        <UAlert
            v-if="statusMessage"
            color="success"
            variant="soft"
            icon="i-lucide-circle-check"
            title="Status"
            :description="statusMessage"
        />

        <USkeleton v-if="isLoading" class="h-96 w-full rounded-lg" />

        <UForm
            v-else
            :state="state"
            :validate="validate"
            class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]"
            @submit="submit"
        >
            <div
                class="space-y-4 rounded-lg border border-default bg-default p-4"
            >
                <UFormField
                    name="title"
                    label="Title"
                    required
                    :error="fieldError('title')"
                >
                    <UInput
                        v-model="state.title"
                        placeholder="Post title"
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
                        placeholder="post-slug"
                        :disabled="isSaving"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    name="excerpt"
                    label="Excerpt"
                    hint="Optional"
                    :error="fieldError('excerpt')"
                >
                    <UTextarea
                        v-model="state.excerpt"
                        placeholder="Short post summary"
                        :rows="3"
                        autoresize
                        :disabled="isSaving"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    name="content"
                    label="Content"
                    required
                    :error="fieldError('content')"
                >
                    <UTextarea
                        v-model="state.content"
                        placeholder="Write post content"
                        :rows="12"
                        autoresize
                        :disabled="isSaving"
                        class="w-full"
                    />
                </UFormField>
            </div>

            <div
                class="space-y-4 rounded-lg border border-default bg-default p-4"
            >
                <UFormField
                    name="image"
                    label="Image"
                    hint="Max 2 MB"
                    :error="fieldError('image')"
                >
                    <div class="space-y-3">
                        <img
                            v-if="imagePreview"
                            :src="imagePreview"
                            alt=""
                            class="aspect-video w-full rounded-md border border-default object-cover"
                        />

                        <UInput
                            type="file"
                            accept="image/*"
                            :disabled="isSaving"
                            @change="selectImage"
                        />
                    </div>
                </UFormField>

                <UFormField
                    name="published_at"
                    label="Published at"
                    hint="Optional"
                    :error="fieldError('published_at')"
                >
                    <UInput
                        v-model="state.published_at"
                        type="datetime-local"
                        :disabled="isSaving"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    name="category_ids"
                    label="Categories"
                    hint="Optional"
                    :error="fieldError('category_ids')"
                >
                    <USelectMenu
                        v-model="state.category_ids"
                        :items="categoryItems"
                        value-key="value"
                        multiple
                        placeholder="Choose categories"
                        :disabled="isSaving"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    name="tag_ids"
                    label="Tags"
                    hint="Optional"
                    :error="fieldError('tag_ids')"
                >
                    <USelectMenu
                        v-model="state.tag_ids"
                        :items="tagItems"
                        value-key="value"
                        multiple
                        placeholder="Choose tags"
                        :disabled="isSaving"
                        class="w-full"
                    />
                </UFormField>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <UButton
                        type="button"
                        color="neutral"
                        variant="ghost"
                        label="Reset"
                        :disabled="isSaving"
                        @click="resetForm"
                    />

                    <UButton
                        type="submit"
                        icon="i-lucide-save"
                        :label="submitLabel"
                        :loading="isSaving"
                    />
                </div>
            </div>
        </UForm>
    </div>
</template>
