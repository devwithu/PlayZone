<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, ThumbsUp, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { show as gameShow } from '@/routes/games';
import {
    destroy as destroyComment,
    update as updateComment,
} from '@/routes/review-comments';
import { destroy, edit } from '@/routes/reviews';
import { store as storeComment } from '@/routes/reviews/comments';
import { destroy as unlike, store as like } from '@/routes/reviews/like';
import { formatKstDate, reviewStatusLabels } from '@/types/review';
import type {
    Pagination,
    RatingCategory,
    Review,
    ReviewComment,
} from '@/types/review';

// W-06 리뷰 상세: 본문, 세부 점수, 추천, 댓글 (REQ-304~307). 공개 화면이라 SSR로 그린다
const props = defineProps<{
    review: Review;
    comments: { items: ReviewComment[]; pagination: Pagination } | null;
    categories: RatingCategory[];
    meta: { description: string; url: string };
}>();

const page = usePage();
const loggedIn = computed(() => page.props.auth.user !== null);
const revealed = ref(!props.review.has_spoiler);
const options = { preserveScroll: true };

const ratingRows = computed(() =>
    props.categories
        .filter((c) => props.review.ratings[c.slug] !== undefined)
        .map((c) => ({ ...c, score: props.review.ratings[c.slug] })),
);

function toggleLike() {
    if (!loggedIn.value) {
        router.visit(login({ query: { redirect: page.url } }));

        return;
    }

    router.visit(
        props.review.is_liked ? unlike(props.review.id) : like(props.review.id),
        options,
    );
}

function removeReview() {
    if (confirm('리뷰를 삭제할까요? 다시 써도 작성 포인트는 받지 않습니다.')) {
        router.visit(destroy(props.review.id));
    }
}

const editingId = ref<number | null>(null);
const editingText = ref('');

function startEdit(comment: ReviewComment) {
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
    <Head :title="`${review.title} - ${review.game?.title ?? '리뷰'}`">
        <meta
            head-key="description"
            name="description"
            :content="meta.description"
        />
        <link head-key="canonical" rel="canonical" :href="meta.url" />
        <meta head-key="og:type" property="og:type" content="article" />
        <meta head-key="og:title" property="og:title" :content="review.title" />
        <meta
            head-key="og:description"
            property="og:description"
            :content="meta.description"
        />
        <meta head-key="og:url" property="og:url" :content="meta.url" />
    </Head>

    <article class="mx-auto w-full max-w-3xl space-y-6">
        <nav v-if="review.game" class="text-sm text-muted-foreground">
            <Link :href="gameShow(review.game.slug)" class="hover:underline">{{
                review.game.title
            }}</Link>
            <span class="mx-1">›</span>
            <span>리뷰</span>
        </nav>

        <div
            v-if="review.status && review.status !== 'published'"
            class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
        >
            이 리뷰는 <b>{{ reviewStatusLabels[review.status] }}</b> 상태라
            나에게만 보입니다.
            <template v-if="review.hidden_reason"
                >사유: {{ review.hidden_reason }}</template
            >
        </div>

        <header class="space-y-3">
            <div class="flex items-start gap-3">
                <span
                    class="rounded-lg bg-amber-100 px-3 py-1.5 text-xl font-bold text-amber-900 dark:bg-amber-900/40 dark:text-amber-200"
                    >★ {{ review.overall_score }}</span
                >
                <h1 class="text-xl font-bold">{{ review.title }}</h1>
            </div>
            <p
                class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-muted-foreground"
            >
                <span class="font-medium text-foreground">{{
                    review.author.nickname
                }}</span>
                <span v-if="review.author.level">{{
                    review.author.level.name
                }}</span>
                <span>{{ review.platform.name }}</span>
                <span
                    >{{ formatKstDate(review.created_at) }}
                    <template v-if="review.edited_at"
                        >(수정됨
                        {{ formatKstDate(review.edited_at) }})</template
                    ></span
                >
                <Badge v-if="review.has_spoiler" variant="secondary"
                    >스포일러</Badge
                >
            </p>
        </header>

        <dl
            v-if="ratingRows.length"
            class="grid grid-cols-2 gap-x-6 gap-y-1 rounded-lg border p-4 text-sm sm:grid-cols-3"
        >
            <div
                v-for="r in ratingRows"
                :key="r.slug"
                class="flex justify-between"
            >
                <dt class="text-muted-foreground">{{ r.name }}</dt>
                <dd class="font-medium">{{ r.score }}</dd>
            </div>
        </dl>

        <div class="text-sm leading-relaxed whitespace-pre-line">
            <template v-if="revealed">{{ review.content }}</template>
            <button
                v-else
                type="button"
                class="w-full rounded-lg border border-dashed py-8 text-muted-foreground"
                @click="revealed = true"
            >
                스포일러가 있는 리뷰입니다. 눌러서 보기
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                v-if="!review.is_mine && review.status !== 'blinded'"
                type="button"
                :variant="review.is_liked ? 'default' : 'outline'"
                :aria-pressed="review.is_liked === true"
                data-test="like-toggle"
                @click="toggleLike"
            >
                <ThumbsUp class="size-4" />
                추천 {{ review.like_count }}
            </Button>
            <span v-else class="text-sm text-muted-foreground"
                >추천 {{ review.like_count }}</span
            >

            <template v-if="review.is_mine && review.status === 'published'">
                <Button variant="ghost" size="sm" as-child class="ml-auto">
                    <Link :href="edit(review.id)"
                        ><Pencil class="size-4" /> 수정</Link
                    >
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    type="button"
                    data-test="review-delete"
                    @click="removeReview"
                >
                    <Trash2 class="size-4" /> 삭제
                </Button>
            </template>
        </div>

        <section v-if="comments" class="space-y-4 border-t pt-6">
            <h2 class="font-semibold">
                댓글
                <span class="text-sm font-normal text-muted-foreground">{{
                    review.comment_count
                }}</span>
            </h2>

            <Form
                v-if="loggedIn"
                v-bind="storeComment.form(review.id)"
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
                    v-for="c in comments.items"
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
