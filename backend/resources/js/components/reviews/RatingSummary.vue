<script setup lang="ts">
import { computed } from 'vue';
import type { RatingSummary } from '@/types/review';

// W-05 점수 분포와 세부 항목 평균 (REQ-105, 리뷰 정책 §9). 공개 리뷰 3개 미만이면 평균 대신 "평가 부족"
const props = defineProps<{ summary: RatingSummary }>();

const rows = computed(() => {
    const max = Math.max(1, ...Object.values(props.summary.distribution));

    return Array.from({ length: 10 }, (_, i) => {
        const score = 10 - i;
        const count = props.summary.distribution[String(score)] ?? 0;

        return { score, count, width: `${(count / max) * 100}%` };
    });
});
</script>

<template>
    <div class="grid gap-6 rounded-lg border p-4 sm:grid-cols-2">
        <div class="space-y-1.5">
            <p class="text-sm font-medium">점수 분포</p>
            <div
                v-for="row in rows"
                :key="row.score"
                class="flex items-center gap-2 text-xs"
            >
                <span class="w-5 text-right text-muted-foreground">{{
                    row.score
                }}</span>
                <div class="h-2 flex-1 overflow-hidden rounded bg-muted">
                    <div
                        class="h-full rounded bg-amber-400"
                        :style="{ width: row.width }"
                    />
                </div>
                <span class="w-6 text-right text-muted-foreground">{{
                    row.count
                }}</span>
            </div>
        </div>

        <div class="space-y-2">
            <p class="text-sm font-medium">세부 평가</p>
            <p
                v-if="summary.rating_avg === null"
                class="text-sm text-muted-foreground"
            >
                리뷰가 3개 이상 모이면 항목별 평균을 보여 드려요.
            </p>
            <dl v-else class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                <template v-for="c in summary.categories" :key="c.slug">
                    <dt class="text-muted-foreground">{{ c.name }}</dt>
                    <dd class="text-right font-medium">
                        {{
                            c.avg_score === null ? '-' : c.avg_score.toFixed(1)
                        }}
                    </dd>
                </template>
            </dl>
        </div>
    </div>
</template>
