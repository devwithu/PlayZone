<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MessageSquare, ThumbsUp } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { show } from '@/routes/reviews';
import { formatKstDate } from '@/types/review';
import type { Review } from '@/types/review';

// 리뷰 목록 한 줄 (W-05). 스포일러 리뷰는 본문을 접어 두고 눌러야 보여 준다 (리뷰 정책 §4)
const props = defineProps<{ review: Review }>();

const revealed = ref(!props.review.has_spoiler);
</script>

<template>
    <article class="space-y-2 py-4" :data-test="`review-${review.id}`">
        <header class="flex items-center gap-2 text-sm">
            <span
                class="rounded bg-amber-100 px-1.5 py-0.5 font-bold text-amber-900 dark:bg-amber-900/40 dark:text-amber-200"
                >★ {{ review.overall_score }}</span
            >
            <Link
                :href="show(review.id)"
                class="min-w-0 truncate font-medium hover:underline"
            >
                {{ review.title }}
            </Link>
            <Badge v-if="review.has_spoiler" variant="secondary"
                >스포일러</Badge
            >
        </header>

        <p
            v-if="revealed"
            class="line-clamp-3 text-sm leading-relaxed whitespace-pre-line text-foreground/90"
        >
            {{ review.content }}
        </p>
        <button
            v-else
            type="button"
            class="text-sm text-muted-foreground underline"
            @click="revealed = true"
        >
            스포일러가 있어요. 눌러서 보기
        </button>

        <footer
            class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
        >
            <span class="font-medium text-foreground/80">{{
                review.author.nickname
            }}</span>
            <span v-if="review.author.level">{{
                review.author.level.name
            }}</span>
            <span>{{ review.platform.name }}</span>
            <span>
                {{ formatKstDate(review.created_at) }}
                <template v-if="review.edited_at">(수정됨)</template>
            </span>
            <span class="flex items-center gap-1">
                <ThumbsUp
                    class="size-3"
                    :class="review.is_liked ? 'fill-current text-primary' : ''"
                />
                {{ review.like_count }}
            </span>
            <span class="flex items-center gap-1">
                <MessageSquare class="size-3" /> {{ review.comment_count }}
            </span>
        </footer>
    </article>
</template>
