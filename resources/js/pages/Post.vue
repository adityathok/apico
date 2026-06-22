<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import type { EditorToolbarItem, FormError, FormSubmitEvent } from '@nuxt/ui';
import axios, { AxiosError } from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { generate as generateArticle } from '@/actions/App/Http/Controllers/ArticleGeneratorController';
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
    image_caption: string | null;
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

type TagOption = SelectOption & {
    slug: string;
};

type PostFormState = {
    title: string;
    slug: string;
    image_caption: string;
    excerpt: string;
    content: string;
    published_at: string;
    category_ids: number[];
    tag_ids: number[];
    tag_names: string;
};

type ValidationResponse = {
    message?: string;
    errors?: Record<string, string[]>;
};

type TopicFormState = {
    topic: string;
};

type GeneratedArticle = {
    title?: string;
    content?: string;
    excerpt?: string;
    tags?: string[];
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
    image_caption: '',
    excerpt: '',
    content: '',
    published_at: '',
    category_ids: [],
    tag_ids: [],
    tag_names: '',
});

const categories = ref<SelectOption[]>([]);
const tags = ref<TagOption[]>([]);
const image = ref<File | null>(null);
const currentImage = ref<string | null>(null);
const postId = ref<number | null>(null);
const isEdit = ref(false);
const isLoading = ref(true);
const isSaving = ref(false);
const serverErrors = ref<Record<string, string>>({});
const statusMessage = ref<string | null>(null);
const loadError = ref<string | null>(null);
const isGenerateModalOpen = ref(false);
const isGenerating = ref(false);
const aiFormMessage = ref<string | null>(null);
const aiResult = ref<GeneratedArticle | null>(null);
const aiRawResult = ref<string | null>(null);
const aiServerErrors = ref<Record<string, string>>({});
const topicState = reactive<TopicFormState>({
    topic: '',
});

const title = computed(() => (isEdit.value ? 'Edit Post' : 'Create Post'));
const submitLabel = computed(() =>
    isEdit.value ? 'Update Post' : 'Create Post',
);

const categoryItems = computed(() => categories.value);
const editorToolbarItems: EditorToolbarItem[][] = [
    [
        {
            kind: 'heading',
            level: 2,
            icon: 'i-lucide-heading-2',
            tooltip: { text: 'Heading' },
            'aria-label': 'Heading',
        },
        {
            kind: 'paragraph',
            icon: 'i-lucide-pilcrow',
            tooltip: { text: 'Paragraph' },
            'aria-label': 'Paragraph',
        },
    ],
    [
        {
            kind: 'mark',
            mark: 'bold',
            icon: 'i-lucide-bold',
            tooltip: { text: 'Bold' },
            'aria-label': 'Bold',
        },
        {
            kind: 'mark',
            mark: 'italic',
            icon: 'i-lucide-italic',
            tooltip: { text: 'Italic' },
            'aria-label': 'Italic',
        },
        {
            kind: 'mark',
            mark: 'strike',
            icon: 'i-lucide-strikethrough',
            tooltip: { text: 'Strike' },
            'aria-label': 'Strike',
        },
    ],
    [
        {
            kind: 'bulletList',
            icon: 'i-lucide-list',
            tooltip: { text: 'Bullet list' },
            'aria-label': 'Bullet list',
        },
        {
            kind: 'orderedList',
            icon: 'i-lucide-list-ordered',
            tooltip: { text: 'Numbered list' },
            'aria-label': 'Numbered list',
        },
        {
            kind: 'blockquote',
            icon: 'i-lucide-quote',
            tooltip: { text: 'Quote' },
            'aria-label': 'Quote',
        },
    ],
    [
        {
            kind: 'link',
            icon: 'i-lucide-link',
            tooltip: { text: 'Link' },
            'aria-label': 'Link',
        },
        {
            kind: 'image',
            icon: 'i-lucide-image',
            tooltip: { text: 'Image URL' },
            'aria-label': 'Image URL',
        },
        {
            kind: 'horizontalRule',
            icon: 'i-lucide-minus',
            tooltip: { text: 'Horizontal line' },
            'aria-label': 'Horizontal line',
        },
    ],
    [
        {
            kind: 'undo',
            icon: 'i-lucide-undo-2',
            tooltip: { text: 'Undo' },
            'aria-label': 'Undo',
        },
        {
            kind: 'redo',
            icon: 'i-lucide-redo-2',
            tooltip: { text: 'Redo' },
            'aria-label': 'Redo',
        },
        {
            kind: 'clearFormatting',
            icon: 'i-lucide-eraser',
            tooltip: { text: 'Clear formatting' },
            'aria-label': 'Clear formatting',
        },
    ],
];

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

const parseTagNames = (value: string): string[] => {
    const names = value
        .split(',')
        .map((tagName) => tagName.trim())
        .filter((tagName) => tagName !== '');
    const uniqueNames = new Map<string, string>();

    names.forEach((tagName) => {
        const slug = slugify(tagName);

        if (slug && !uniqueNames.has(slug)) {
            uniqueNames.set(slug, tagName);
        }
    });

    return Array.from(uniqueNames.values());
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

const validateTopic = (formState: Partial<TopicFormState>): FormError[] => {
    const errors: FormError[] = [];

    if (!formState.topic?.trim()) {
        errors.push({ name: 'topic', message: 'Topic wajib diisi.' });
    }

    return errors;
};

const fieldError = (name: string): string | undefined => {
    return serverErrors.value[name];
};

const aiFieldError = (name: string): string | undefined => {
    return aiServerErrors.value[name];
};

const resetForm = (): void => {
    state.title = '';
    state.slug = '';
    state.image_caption = '';
    state.excerpt = '';
    state.content = '';
    state.published_at = '';
    state.category_ids = [];
    state.tag_ids = [];
    state.tag_names = '';
    image.value = null;
    currentImage.value = null;
    postId.value = null;
    isEdit.value = false;
};

const resetAiGenerator = (): void => {
    topicState.topic = '';
    aiFormMessage.value = null;
    aiResult.value = null;
    aiRawResult.value = null;
    aiServerErrors.value = {};
};

const fillForm = (postData: Post): void => {
    state.title = postData.title;
    state.slug = postData.slug;
    state.image_caption = postData.image_caption ?? '';
    state.excerpt = postData.excerpt ?? '';
    state.content = postData.content;
    state.published_at = formatDateTimeLocal(postData.published_at);
    state.category_ids = (postData.categories ?? []).map(
        (category) => category.id,
    );
    state.tag_ids = (postData.tags ?? []).map((tag) => tag.id);
    state.tag_names = (postData.tags ?? []).map((tag) => tag.name).join(', ');
    currentImage.value = postData.image;
    postId.value = postData.id;
    isEdit.value = true;
};

const fetchTaxonomies = async (): Promise<void> => {
    const [categoryResponse, tagResponse] = await Promise.all([
        axios.get<CollectionResponse<Taxonomy>>('/ajax/categories'),
        axios.get<CollectionResponse<Taxonomy>>('/ajax/tags'),
    ]);

    categories.value = categoryResponse.data.data.map((category) => ({
        label: category.name,
        value: category.id,
    }));

    tags.value = tagResponse.data.data.map((tag) => ({
        label: tag.name,
        value: tag.id,
        slug: tag.slug,
    }));
};

const resolveTagIds = async (): Promise<void> => {
    const tagNames = parseTagNames(state.tag_names);
    const tagIds: number[] = [];

    for (const tagName of tagNames) {
        const slug = slugify(tagName);
        const existingTag = tags.value.find(
            (tag) =>
                tag.slug === slug ||
                tag.label.toLowerCase() === tagName.toLowerCase(),
        );

        if (existingTag) {
            tagIds.push(existingTag.value);

            continue;
        }

        const response = await axios.post<ResourceResponse<Taxonomy>>(
            '/ajax/tags',
            {
                name: tagName,
                slug,
            },
        );
        const tag = response.data.data;

        tags.value.unshift({
            label: tag.name,
            value: tag.id,
            slug: tag.slug,
        });
        tagIds.push(tag.id);
    }

    state.tag_ids = tagIds;
    state.tag_names = tagNames.join(', ');
};

const fetchPost = async (id: number): Promise<void> => {
    try {
        const response = await axios.get<ResourceResponse<Post>>(
            `/ajax/posts/${id}`,
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
    image_caption: state.image_caption,
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
    formData.append('image_caption', state.image_caption);
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
            '/ajax/posts',
            buildFormData(),
        );

        return response.data.data;
    }

    const response = await axios.post<ResourceResponse<Post>>(
        '/ajax/posts',
        buildPayload(),
    );

    return response.data.data;
};

const updatePost = async (id: number): Promise<Post> => {
    if (image.value) {
        await axios.post<ResourceResponse<Post>>(
            `/ajax/posts/${id}`,
            buildFormData('PATCH'),
        );

        const response = await axios.patch<ResourceResponse<Post>>(
            `/ajax/posts/${id}`,
            buildPayload(),
        );

        return response.data.data;
    }

    const response = await axios.patch<ResourceResponse<Post>>(
        `/ajax/posts/${id}`,
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

const handleAiValidationErrors = (error: unknown): void => {
    if (!(error instanceof AxiosError) || error.response?.status !== 422) {
        aiFormMessage.value = 'Artikel AI gagal dibuat.';

        return;
    }

    const response = error.response.data as ValidationResponse;
    const errors = response.errors ?? {};

    aiServerErrors.value = Object.fromEntries(
        Object.entries(errors).map(([name, messages]) => [
            name,
            messages[0] ?? 'Invalid value.',
        ]),
    );
};

const normalizeGeneratedArticle = (
    value: unknown,
): { article: GeneratedArticle | null; raw: string | null } => {
    if (typeof value === 'string') {
        return {
            article: {
                content: value,
            },
            raw: value,
        };
    }

    if (!value || typeof value !== 'object') {
        return { article: null, raw: null };
    }

    const payload = value as Record<string, unknown>;
    const title =
        typeof payload.title === 'string' ? payload.title.trim() : undefined;
    const content =
        typeof payload.content === 'string'
            ? payload.content.trim()
            : undefined;
    const excerpt =
        typeof payload.excerpt === 'string'
            ? payload.excerpt.trim()
            : undefined;
    const tags = Array.isArray(payload.tags)
        ? payload.tags.filter(
              (tag): tag is string =>
                  typeof tag === 'string' && tag.trim() !== '',
          )
        : undefined;

    return {
        article: {
            title,
            content,
            excerpt,
            tags,
        },
        raw: JSON.stringify(payload, null, 2),
    };
};

const applyGeneratedArticle = (article: GeneratedArticle): void => {
    if (article.title) {
        state.title = article.title;
    }

    if (article.excerpt) {
        state.excerpt = article.excerpt;
    }

    if (article.content) {
        state.content = article.content;
    }

    if (article.tags && article.tags.length > 0) {
        state.tag_names = article.tags.join(', ');
    }

    if (!isEdit.value) {
        state.slug = slugify(state.title);
    }
};

const openGenerateModal = (): void => {
    resetAiGenerator();
    isGenerateModalOpen.value = true;
};

const closeGenerateModal = (): void => {
    if (isGenerating.value) {
        return;
    }

    isGenerateModalOpen.value = false;
    resetAiGenerator();
};

const submitGenerateArticle = async (
    _event: FormSubmitEvent<TopicFormState>,
): Promise<void> => {
    isGenerating.value = true;
    aiFormMessage.value = null;
    aiResult.value = null;
    aiRawResult.value = null;
    aiServerErrors.value = {};

    try {
        const route = generateArticle();
        const response = await axios({
            url: route.url,
            method: route.method,
            data: {
                topic: topicState.topic,
            },
        });
        const payload =
            response.data && typeof response.data === 'object' && 'data' in response.data
                ? response.data.data
                : response.data;
        const { article, raw } = normalizeGeneratedArticle(payload);

        aiResult.value = article;
        aiRawResult.value = raw;
    } catch (error) {
        handleAiValidationErrors(error);
    } finally {
        isGenerating.value = false;
    }
};

const useGeneratedArticle = (): void => {
    if (!aiResult.value) {
        return;
    }

    applyGeneratedArticle(aiResult.value);
    statusMessage.value = 'Hasil artikel AI berhasil dimasukkan ke form.';
    isGenerateModalOpen.value = false;
    resetAiGenerator();
};

const submit = async (): Promise<void> => {
    isSaving.value = true;
    loadError.value = null;
    statusMessage.value = null;
    serverErrors.value = {};

    try {
        const editingPostId = isEdit.value ? postId.value : null;
        await resolveTagIds();

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

            <div class="flex items-center justify-end gap-2">                
                <UButton
                    icon="i-lucide-brain"
                    color="primary"
                    label="Generate AI"
                    :disabled="isSaving"
                    @click="openGenerateModal"
                />
                <UButton
                    :to="post()"
                    icon="i-lucide-plus"
                    color="info"
                    label="Add"
                />
                <UButton
                    :to="posts()"
                    icon="i-lucide-arrow-left"
                    color="neutral"
                    variant="outline"
                    label="Back"
                />
            </div>
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
                    <UEditor
                        v-slot="{ editor }"
                        v-model="state.content"
                        content-type="html"
                        :disabled="isSaving"
                        class="min-h-96 w-full rounded-md border border-default bg-default"
                    >
                        <UEditorToolbar
                            :editor="editor"
                            :items="editorToolbarItems"
                            class="border-b border-default p-2"
                        />
                        <UEditorSuggestionMenu :editor="editor" />
                        <UEditorEmojiMenu :editor="editor" />
                        <UEditorDragHandle :editor="editor" />
                    </UEditor>
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
                    name="image_caption"
                    label="Image caption"
                    hint="Optional"
                    :error="fieldError('image_caption')"
                >
                    <UTextarea
                        v-model="state.image_caption"
                        placeholder="Caption for the featured image"
                        :rows="2"
                        autoresize
                        :disabled="isSaving"
                        class="w-full"
                    />
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
                    name="tag_names"
                    label="Tags"
                    hint="Optional"
                    :error="fieldError('tag_ids') ?? fieldError('tag_names')"
                >
                    <UTextarea
                        v-model="state.tag_names"
                        placeholder="laravel, api, tutorial"
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

        <UModal
            v-model:open="isGenerateModalOpen"
            title="Generate Article AI"
            description="Masukkan topik artikel, lihat hasilnya dulu, lalu klik Gunakan untuk mengisi form post."
            :ui="{ footer: 'justify-end' }"
        >
            <template #body>
                <UAlert
                    v-if="aiFormMessage"
                    color="error"
                    variant="soft"
                    icon="i-lucide-circle-alert"
                    title="Ada masalah"
                    :description="aiFormMessage"
                    class="mb-4"
                />

                <UForm
                    id="article-generator-form"
                    :state="topicState"
                    :validate="validateTopic"
                    class="space-y-4"
                    @submit="submitGenerateArticle"
                >
                    <UFormField
                        name="topic"
                        label="Topic"
                        required
                        :error="aiFieldError('topic')"
                    >
                        <UTextarea
                            v-model="topicState.topic"
                            placeholder="Contoh: strategi SEO untuk blog Laravel"
                            :rows="4"
                            autoresize
                            :disabled="isGenerating"
                            class="w-full"
                        />
                    </UFormField>
                </UForm>

                <div v-if="aiResult || aiRawResult" class="mt-6 space-y-4">
                    <USeparator label="Hasil Generate" />

                    <UFormField
                        v-if="aiResult?.title"
                        label="Title"
                    >
                        <UTextarea
                            :model-value="aiResult.title"
                            :rows="2"
                            readonly
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        v-if="aiResult?.excerpt"
                        label="Excerpt"
                    >
                        <UTextarea
                            :model-value="aiResult.excerpt"
                            :rows="3"
                            readonly
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        v-if="aiResult?.content"
                        label="Content"
                    >
                        <UTextarea
                            :model-value="aiResult.content"
                            :rows="12"
                            readonly
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        v-if="aiResult?.tags?.length"
                        label="Tags"
                    >
                        <UInput
                            :model-value="aiResult.tags.join(', ')"
                            readonly
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        v-if="!aiResult?.content && aiRawResult"
                        label="Response"
                    >
                        <UTextarea
                            :model-value="aiRawResult"
                            :rows="12"
                            readonly
                            class="w-full font-mono text-xs"
                        />
                    </UFormField>
                </div>
            </template>

            <template #footer>
                <UButton
                    label="Close"
                    color="neutral"
                    variant="outline"
                    :disabled="isGenerating"
                    @click="closeGenerateModal"
                />

                <UButton
                    v-if="aiResult"
                    color="primary"
                    variant="soft"
                    icon="i-lucide-check"
                    label="Gunakan"
                    :disabled="isGenerating"
                    @click="useGeneratedArticle"
                />

                <UButton
                    type="submit"
                    form="article-generator-form"
                    icon="i-lucide-sparkles"
                    label="Generate"
                    :loading="isGenerating"
                />
            </template>
        </UModal>
    </div>
</template>
