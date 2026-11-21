<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ImagePlus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { board as boardRoute } from '@/routes/community';
import { show, store, update } from '@/routes/posts';
import type { Board, PostImage } from '@/types/community';

// W-12 게시글 작성·수정 (REQ-403, 408). 이미지는 본문 아래에 붙는 첨부로 올린다
const props = defineProps<{
    boards: Board[];
    board: Board;
    post: {
        id: number;
        title: string;
        content: string;
        images: PostImage[];
    } | null;
    limits: {
        max_images: number;
        image_max_mb: number;
        min_content_for_points: number;
    };
}>();

const editing = computed(() => props.post !== null);

const form = useForm<{
    board_id: number;
    title: string;
    content: string;
    images: File[];
    remove_attachment_ids: number[];
}>({
    board_id: props.board.id,
    title: props.post?.title ?? '',
    content: props.post?.content ?? '',
    images: [],
    remove_attachment_ids: [],
});

const keptImages = computed(() =>
    (props.post?.images ?? []).filter(
        (i) => !form.remove_attachment_ids.includes(i.id),
    ),
);
const remaining = computed(
    () =>
        props.limits.max_images - keptImages.value.length - form.images.length,
);
const fileInput = ref<HTMLInputElement | null>(null);
const pickError = ref<string | null>(null);

function addFiles(event: Event) {
    const files = Array.from((event.target as HTMLInputElement).files ?? []);
    pickError.value = null;

    for (const file of files) {
        if (
            form.images.length + keptImages.value.length >=
            props.limits.max_images
        ) {
            pickError.value = `이미지는 ${props.limits.max_images}개까지 올릴 수 있습니다.`;
            break;
        }

        if (file.size > props.limits.image_max_mb * 1024 * 1024) {
            pickError.value = `${file.name}: 이미지 하나는 ${props.limits.image_max_mb}MB 이하여야 합니다.`;
            continue;
        }

        form.images.push(file);
    }

    (event.target as HTMLInputElement).value = '';
}

function submit() {
    if (props.post) {
        // 파일 업로드는 multipart라 POST + _method=put으로 보낸다
        form.transform((data) => ({
            ...data,
            board_id: undefined,
            _method: 'put',
        })).post(update(props.post.id).url, { forceFormData: true });
    } else {
        form.transform((data) => ({
            ...data,
            remove_attachment_ids: undefined,
        })).submit(store(), { forceFormData: true });
    }
}

function imageError(): string | undefined {
    const errors = form.errors as Record<string, string>;

    return (
        errors.images ??
        Object.entries(errors).find(([k]) => k.startsWith('images.'))?.[1]
    );
}
</script>

<template>
    <Head :title="editing ? '글 수정' : `${board.name} 글쓰기`" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <nav class="text-sm text-muted-foreground">
            <Link :href="boardRoute(board.slug)" class="hover:underline">{{
                board.name
            }}</Link>
            <span class="mx-1">›</span>
            <span>{{ editing ? '글 수정' : '글쓰기' }}</span>
        </nav>

        <form class="space-y-5" @submit.prevent="submit">
            <div v-if="!editing" class="space-y-2">
                <Label for="board_id">게시판</Label>
                <select
                    id="board_id"
                    v-model="form.board_id"
                    class="h-9 w-full rounded-md border bg-transparent px-3 text-sm sm:w-60"
                >
                    <option v-for="b in boards" :key="b.id" :value="b.id">
                        {{ b.name }}
                    </option>
                </select>
                <InputError :message="form.errors.board_id" />
            </div>

            <div class="space-y-2">
                <Label for="title">제목</Label>
                <Input
                    id="title"
                    v-model="form.title"
                    maxlength="100"
                    placeholder="제목 (2~100자)"
                />
                <InputError :message="form.errors.title" />
            </div>

            <div class="space-y-2">
                <Label for="content">본문</Label>
                <textarea
                    id="content"
                    v-model="form.content"
                    rows="14"
                    maxlength="20000"
                    class="w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError :message="form.errors.content" />
                <p v-if="!editing" class="text-xs text-muted-foreground">
                    본문이 {{ limits.min_content_for_points }}자 이상이면 작성
                    포인트를 받아요 (하루 횟수 제한 안에서).
                </p>
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <Label>이미지</Label>
                    <span class="text-xs text-muted-foreground">
                        jpg·png·webp·gif, 1개 {{ limits.image_max_mb }}MB 이하,
                        {{ limits.max_images }}개까지
                    </span>
                </div>
                <ul class="flex flex-wrap gap-2">
                    <li
                        v-for="img in keptImages"
                        :key="`old-${img.id}`"
                        class="relative"
                    >
                        <img
                            :src="img.url"
                            :alt="img.name"
                            class="size-20 rounded border object-cover"
                        />
                        <button
                            type="button"
                            class="absolute -top-2 -right-2 rounded-full border bg-background p-0.5"
                            :aria-label="`${img.name} 삭제`"
                            @click="form.remove_attachment_ids.push(img.id)"
                        >
                            <X class="size-3" />
                        </button>
                    </li>
                    <li
                        v-for="(file, i) in form.images"
                        :key="`new-${i}`"
                        class="relative"
                    >
                        <div
                            class="flex size-20 items-center justify-center rounded border bg-muted p-1 text-center text-[10px] break-all"
                        >
                            {{ file.name }}
                        </div>
                        <button
                            type="button"
                            class="absolute -top-2 -right-2 rounded-full border bg-background p-0.5"
                            :aria-label="`${file.name} 빼기`"
                            @click="form.images.splice(i, 1)"
                        >
                            <X class="size-3" />
                        </button>
                    </li>
                    <li v-if="remaining > 0">
                        <button
                            type="button"
                            class="flex size-20 flex-col items-center justify-center gap-1 rounded border border-dashed text-xs text-muted-foreground"
                            @click="fileInput?.click()"
                        >
                            <ImagePlus class="size-5" /> 추가
                        </button>
                    </li>
                </ul>
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    multiple
                    class="hidden"
                    @change="addFiles"
                />
                <InputError :message="pickError ?? imageError()" />
            </div>

            <div class="flex justify-end gap-2">
                <Button variant="ghost" as-child>
                    <Link :href="post ? show(post.id) : boardRoute(board.slug)"
                        >취소</Link
                    >
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="post-submit"
                >
                    {{ editing ? '수정하기' : '등록하기' }}
                </Button>
            </div>
        </form>
    </div>
</template>
