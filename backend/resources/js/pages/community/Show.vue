<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, ThumbsUp, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { board as boardRoute } from '@/routes/community';
import {
    destroy as destroyComment,
    update as updateComment,
} from '@/routes/post-comments';
import { destroy, edit } from '@/routes/posts';
import { store as storeComment } from '@/routes/posts/comments';
import { destroy as unlike, store as like } from '@/routes/posts/like';
import { formatKstDate, reviewStatusLabels } from '@/types/review';
import type { PostComment, PostDetail } from '@/types/community';

// W-11 게시글 상세: 본문·이미지, 추천, 본인 글 수정·삭제, 댓글 (REQ-404~406). 공개 화면이라 SSR로 그린다
const props = defineProps<{
    post: PostDetail;
    comments: PostComment[] | null;
    meta: { description: string; image: string | null; url: string };
}>();

const page = usePage();
const loggedIn = computed(() => page.props.auth.user !== null);
const options = { preserveScroll: true };

function toggleLike() {
    if (!loggedIn.value) {
        router.visit(login({ query: { redirect: page.url } }));

        return;
    }

    router.visit(
        props.post.is_liked ? unlike(props.post.id) : like(props.post.id),
        options,
    );
}

function removePost() {
    if (confirm('글을 삭제할까요?')) {
        router.visit(destroy(props.post.id));
    }
}

const editingId = ref<number | null>(null);
const editingText = ref('');

function startEdit(comment: PostComment) {
    editingId.value = comment.id;
    editingText.value = comment.content ?? '';
}

function saveEdit(id: number) {
    router.visit(updateComment(id), {
        ...options,
        data: { content: editingText.value },
        onSuccess: () => (editingId.value = null),
    });
}

function removeComment(id: number) {
    if (confirm('댓글을 삭제할까요?')) {
        router.visit(destroyComment(id), options);
    }
}
</script>

<template>
    <Head :title="post.title">
        <meta
            head-key="description"
            name="description"
            :content="meta.description"
        />
        <link head-key="canonical" rel="canonical" :href="meta.url" />
        <meta head-key="og:type" property="og:type" content="article" />
        <meta head-key="og:title" property="og:title" :content="post.title" />
        <meta
            head-key="og:description"
            property="og:description"
            :content="meta.description"
        />
        <meta head-key="og:url" property="og:url" :content="meta.url" />
        <meta
            v-if="meta.image"
            head-key="og:image"
            property="og:image"
            :content="meta.image"
        />
    </Head>

    <article class="mx-auto w-full max-w-3xl space-y-6">
        <nav class="text-sm text-muted-foreground">
            <Link :href="boardRoute(post.board.slug)" class="hover:underline">{{
                post.board.name
            }}</Link>
        </nav>

        <div
            v-if="post.status && post.status !== 'published'"
            class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            이 글은 <b>{{ reviewStatusLabels[post.status] }}</b> 상태라 나에게만
            보입니다.
            <template v-if="post.hidden_reason"
                >사유: {{ post.hidden_reason }}</template
            >
        </div>

        <header class="space-y-2 border-b pb-4">
            <h1 class="text-xl font-bold">{{ post.title }}</h1>
            <p
                class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-muted-foreground"
            >
                <span class="font-medium text-foreground">{{
                    post.author.nickname
                }}</span>
                <span v-if="post.author.level">{{
                    post.author.level.name
                }}</span>
                <span>
                    {{ formatKstDate(post.created_at) }}
                    <template v-if="post.edited_at">(수정됨)</template>
                </span>
                <span>조회 {{ post.view_count }}</span>
            </p>
        </header>

        <div class="text-sm leading-relaxed whitespace-pre-line">
            {{ post.content }}
        </div>

        <div v-if="post.images.length" class="space-y-3">
            <img
                v-for="img in post.images"
                :key="img.id"
                :src="img.url"
                :alt="img.name"
                loading="lazy"
                class="max-h-[600px] max-w-full rounded-lg border"
            />
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                v-if="!post.is_mine && post.status !== 'blinded'"
                type="button"
                :variant="post.is_liked ? 'default' : 'outline'"
                :aria-pressed="post.is_liked === true"
                data-test="like-toggle"
                @click="toggleLike"
            >
                <ThumbsUp class="size-4" /> 추천 {{ post.like_count }}
            </Button>
            <span v-else class="text-sm text-muted-foreground"
                >추천 {{ post.like_count }}</span
            >

            <template v-if="post.is_mine && post.status === 'published'">
                <Button variant="ghost" size="sm" as-child class="ml-auto">
                    <Link :href="edit(post.id)"
                        ><Pencil class="size-4" /> 수정</Link
                    >
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    type="button"
                    @click="removePost"
                >
                    <Trash2 class="size-4" /> 삭제
                </Button>
            </template>
        </div>

        <section v-if="comments" class="space-y-4 border-t pt-6">
            <h2 class="font-semibold">
                댓글
                <span class="text-sm font-normal text-muted-foreground">{{
                    post.comment_count
                }}</span>
            </h2>

            <Form
                v-if="loggedIn"
                v-bind="storeComment.form(post.id)"
                :options="{ preserveScroll: true }"
                reset-on-success
                class="space-y-2"
                #default="{ errors, processing }"
            >
                <textarea
                    name="content"
                    rows="2"
                    maxlength="500"
                    required
                    placeholder="댓글을 남겨 주세요 (500자 이내)"
                    class="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                />
                <InputError :message="errors.content" />
                <div class="flex justify-end">
                    <Button type="submit" size="sm" :disabled="processing"
                        >등록</Button
                    >
                </div>
            </Form>
            <p v-else class="text-sm text-muted-foreground">
                <Link
                    :href="login({ query: { redirect: page.url } })"
                    class="underline"
                    >로그인</Link
                >하면 댓글을 쓸 수 있어요.
            </p>

            <ul class="divide-y">
                <li
                    v-for="c in comments"
                    :key="c.id"
                    class="space-y-1 py-3 text-sm"
                >
                    <template v-if="c.status === 'published'">
                        <p
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span class="font-medium text-foreground">{{
                                c.author?.nickname
                            }}</span>
                            <span>{{ formatKstDate(c.created_at) }}</span>
                            <span v-if="c.is_edited">(수정됨)</span>
                            <span v-if="c.is_mine" class="ml-auto flex gap-2">
                                <button
                                    type="button"
                                    class="hover:underline"
                                    @click="startEdit(c)"
                                >
                                    수정
                                </button>
                                <button
                                    type="button"
                                    class="hover:underline"
                                    @click="removeComment(c.id)"
                                >
                                    삭제
                                </button>
                            </span>
                        </p>
                        <div v-if="editingId === c.id" class="space-y-2">
                            <textarea
                                v-model="editingText"
                                rows="2"
                                maxlength="500"
                                class="w-full rounded-md border bg-transparent px-3 py-2 text-sm"
                            />
                            <div class="flex justify-end gap-2">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    type="button"
                                    @click="editingId = null"
                                    >취소</Button
                                >
                                <Button
                                    size="sm"
                                    type="button"
                                    @click="saveEdit(c.id)"
                                    >저장</Button
                                >
                            </div>
                        </div>
                        <p v-else class="whitespace-pre-line">
                            {{ c.content }}
                        </p>
                    </template>
                    <p v-else class="text-muted-foreground">
                        {{ c.placeholder }}
                    </p>
                </li>
            </ul>
        </section>
    </article>
</template>
