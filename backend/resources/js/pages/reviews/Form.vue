<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show as gameShow } from '@/routes/games';
import { store } from '@/routes/games/reviews';
import { show as reviewShow, update } from '@/routes/reviews';
import type { Taxonomy } from '@/types/game';
import type { RatingCategory } from '@/types/review';

// W-07 리뷰 작성·수정 (REQ-301~303, 리뷰 정책 §4). 검증은 서버(ReviewWriteRequest)가 하고 오류를 항목 아래에 보여 준다
const props = defineProps<{
    game: { id: number; title: string; slug: string };
    platforms: Taxonomy[];
    categories: RatingCategory[];
    review: {
        id: number;
        title: string;
        content: string;
        platform_id: number;
        overall_score: number;
        ratings: Record<string, number>;
        has_spoiler: boolean;
    } | null;
}>();

const editing = computed(() => props.review !== null);
const scores = Array.from({ length: 10 }, (_, i) => i + 1);

const form = useForm({
    title: props.review?.title ?? '',
    content: props.review?.content ?? '',
    platform_id:
        props.review?.platform_id ??
        (props.platforms.length === 1 ? props.platforms[0].id : null),
    overall_score: props.review?.overall_score ?? null,
    ratings: Object.fromEntries(
        props.categories.map((c) => [
            c.slug,
            props.review?.ratings[c.slug] ?? null,
        ]),
    ) as Record<string, number | null>,
    has_spoiler: props.review?.has_spoiler ?? false,
});

const contentLength = computed(() => form.content.trim().length);

function submit() {
    form.submit(props.review ? update(props.review.id) : store(props.game.id), {
        preserveScroll: true,
    });
}

/** 세부 점수 오류는 'ratings.graphics'처럼 온다 */
function ratingError(slug: string): string | undefined {
    return (form.errors as Record<string, string>)[`ratings.${slug}`];
}
</script>

<template>
    <Head :title="editing ? '리뷰 수정' : `${game.title} 리뷰 쓰기`" />

    <div class="mx-auto w-full max-w-2xl space-y-6">
        <nav class="text-sm text-muted-foreground">
            <Link :href="gameShow(game.slug)" class="hover:underline">{{
                game.title
            }}</Link>
            <span class="mx-1">›</span>
            <span>{{ editing ? '리뷰 수정' : '리뷰 쓰기' }}</span>
        </nav>

        <form class="space-y-6" @submit.prevent="submit">
            <fieldset class="space-y-2">
                <legend class="text-sm font-medium">
                    종합 점수 <span class="text-destructive">*</span>
                </legend>
                <div class="flex flex-wrap gap-1" role="radiogroup">
                    <Button
                        v-for="s in scores"
                        :key="s"
                        type="button"
                        size="icon"
                        role="radio"
                        :aria-checked="form.overall_score === s"
                        :variant="
                            form.overall_score === s ? 'default' : 'outline'
                        "
                        :data-test="`overall-${s}`"
                        @click="form.overall_score = s"
                    >
                        {{ s }}
                    </Button>
                </div>
                <InputError :message="form.errors.overall_score" />
            </fieldset>

            <div class="space-y-2">
                <Label for="platform_id"
                    >플레이한 플랫폼
                    <span class="text-destructive">*</span></Label
                >
                <select
                    id="platform_id"
                    v-model="form.platform_id"
                    class="h-9 w-full rounded-md border bg-transparent px-3 text-sm"
                >
                    <option :value="null" disabled>선택해 주세요</option>
                    <option v-for="p in platforms" :key="p.id" :value="p.id">
                        {{ p.name }}
                    </option>
                </select>
                <InputError :message="form.errors.platform_id" />
            </div>

            <div class="space-y-2">
                <Label for="title"
                    >제목 <span class="text-destructive">*</span></Label
                >
                <Input
                    id="title"
                    v-model="form.title"
                    maxlength="50"
                    placeholder="한 줄로 요약해 주세요 (2~50자)"
                />
                <InputError :message="form.errors.title" />
            </div>

            <div class="space-y-2">
                <div class="flex items-baseline justify-between">
                    <Label for="content"
                        >본문 <span class="text-destructive">*</span></Label
                    >
                    <span
                        class="text-xs"
                        :class="
                            contentLength < 50
                                ? 'text-muted-foreground'
                                : 'text-foreground'
                        "
                        >{{ contentLength.toLocaleString() }} / 5,000자 (최소
                        50자)</span
                    >
                </div>
                <textarea
                    id="content"
                    v-model="form.content"
                    rows="10"
                    maxlength="5000"
                    class="w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    placeholder="플레이하며 좋았던 점과 아쉬운 점을 알려 주세요."
                />
                <InputError :message="form.errors.content" />
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox v-model="form.has_spoiler" />
                    스포일러가 있어요 (본문을 접어서 보여 줍니다)
                </label>
            </div>

            <fieldset class="space-y-3">
                <legend class="text-sm font-medium">
                    세부 점수
                    <span class="font-normal text-muted-foreground"
                        >(선택, 매긴 항목만 저장)</span
                    >
                </legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="c in categories"
                        :key="c.slug"
                        class="space-y-1"
                    >
                        <div class="flex items-center gap-2">
                            <label
                                :for="`rating-${c.slug}`"
                                class="w-14 text-sm"
                                >{{ c.name }}</label
                            >
                            <select
                                :id="`rating-${c.slug}`"
                                v-model="form.ratings[c.slug]"
                                class="h-9 flex-1 rounded-md border bg-transparent px-3 text-sm"
                            >
                                <option :value="null">선택 안 함</option>
                                <option v-for="s in scores" :key="s" :value="s">
                                    {{ s }}점
                                </option>
                            </select>
                        </div>
                        <InputError :message="ratingError(c.slug)" />
                    </div>
                </div>
                <InputError :message="form.errors.ratings" />
            </fieldset>

            <div class="flex justify-end gap-2">
                <Button variant="ghost" as-child>
                    <Link
                        :href="
                            review ? reviewShow(review.id) : gameShow(game.slug)
                        "
                        >취소</Link
                    >
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="review-submit"
                >
                    {{ editing ? '수정하기' : '등록하기' }}
                </Button>
            </div>
        </form>
    </div>
</template>
