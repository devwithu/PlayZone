<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Pager from '@/components/mypage/Pager.vue';
import { Button } from '@/components/ui/button';
import { index as games, show as gameShow } from '@/routes/games';
import { reviews as reviewsRoute } from '@/routes/mypage';
import { show } from '@/routes/reviews';
import { formatKstDate, reviewStatusLabels } from '@/types/review';
import type { Pagination, Review } from '@/types/review';

// W-17 내 리뷰 탭 (REQ-603, 313): 상태 배지와 숨김 사유
defineProps<{ reviews: { items: Review[]; pagination: Pagination } }>();

const badge: Record<string, string> = {
    published: 'border-emerald-300 text-emerald-700 dark:text-emerald-300',
    blinded: 'border-amber-300 text-amber-700 dark:text-amber-300',
    hidden: 'border-red-300 text-red-700 dark:text-red-300',
};

function pageUrl(p: number): string {
    return reviewsRoute({ query: p > 1 ? { page: p } : {} }).url;
}
</script>

<template>
    <Head title="내 리뷰" />

    <div class="space-y-4">
        <p class="text-sm text-muted-foreground">
            리뷰 {{ reviews.pagination.total.toLocaleString() }}개
        </p>

        <div
            v-if="!reviews.items.length"
            class="space-y-3 rounded-lg border border-dashed py-16 text-center text-sm text-muted-foreground"
        >
            <p>아직 작성한 리뷰가 없어요.</p>
            <Button as-child variant="outline" size="sm">
                <Link :href="games()">리뷰할 게임 찾기</Link>
            </Button>
        </div>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="r in reviews.items"
                :key="r.id"
                class="space-y-1 p-4"
                :data-test="`my-review-${r.id}`"
            >
                <p
                    class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                >
                    <Link
                        v-if="r.game"
                        :href="gameShow(r.game.slug)"
                        class="font-medium text-foreground hover:underline"
                        >{{ r.game.title }}</Link
                    >
                    <span>{{ formatKstDate(r.created_at) }}</span>
                    <span
                        v-if="r.status"
                        class="rounded-full border px-2 py-0.5"
                        :class="badge[r.status]"
                        >{{ reviewStatusLabels[r.status] }}</span
                    >
                </p>
                <Link
                    :href="show(r.id)"
                    class="flex items-baseline gap-2 hover:underline"
                >
                    <span class="font-bold text-primary"
                        >★{{ r.overall_score }}</span
                    >
                    <span class="truncate font-medium">{{ r.title }}</span>
                </Link>
                <p class="text-xs text-muted-foreground">
                    추천 {{ r.like_count }} · 댓글 {{ r.comment_count }}
                </p>
                <p
                    v-if="r.status && r.status !== 'published'"
                    class="text-xs text-amber-700 dark:text-amber-300"
                >
                    {{
                        r.status === 'blinded'
                            ? '신고가 많아 검토 중입니다. 검토가 끝날 때까지 나에게만 보여요.'
                            : '관리자가 숨긴 리뷰입니다.'
                    }}
                    <template v-if="r.hidden_reason"
                        >사유: {{ r.hidden_reason }}</template
                    >
                </p>
            </li>
        </ul>

        <Pager :pagination="reviews.pagination" :href="pageUrl" />
    </div>
</template>
