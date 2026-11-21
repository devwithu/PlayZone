<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { formatKstDate } from '@/types/review';
import {
    attendance as attendanceRoute,
    favorites,
    points,
    posts,
    reviews,
} from '@/routes/mypage';
import type { PointTransaction } from '@/types/mypage';

// W-17 대시보드 탭 (REQ-601, 504): 출석, 작성 수, 관심 게임 수, 최근 포인트
const props = defineProps<{
    attendance: {
        attended_today: boolean;
        streak_days: number;
        month_count: number;
    };
    counts: { reviews: number; posts: number; favorites: number };
    recentPoints: PointTransaction[];
}>();

const cards = [
    {
        label: '이번 달 출석',
        value: `${props.attendance.month_count}일`,
        sub: props.attendance.attended_today
            ? `오늘 출석 완료 · ${props.attendance.streak_days}일 연속`
            : '오늘 아직 출석하지 않았어요',
        href: attendanceRoute(),
    },
    {
        label: '작성한 리뷰',
        value: `${props.counts.reviews}개`,
        href: reviews(),
    },
    { label: '작성한 글', value: `${props.counts.posts}개`, href: posts() },
    {
        label: '관심 게임',
        value: `${props.counts.favorites}개`,
        href: favorites(),
    },
];
</script>

<template>
    <Head title="마이페이지" />

    <div class="space-y-6">
        <ul class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <li v-for="card in cards" :key="card.label">
                <Link
                    :href="card.href"
                    class="block h-full rounded-lg border p-4 hover:bg-muted/50"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ card.label }}
                    </p>
                    <p class="mt-1 text-2xl font-bold">{{ card.value }}</p>
                    <p
                        v-if="card.sub"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ card.sub }}
                    </p>
                </Link>
            </li>
        </ul>

        <section class="space-y-2">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">최근 포인트</h2>
                <Link
                    :href="points()"
                    class="text-sm text-muted-foreground hover:underline"
                    >전체 내역</Link
                >
            </div>
            <ul v-if="recentPoints.length" class="divide-y rounded-lg border">
                <li
                    v-for="t in recentPoints"
                    :key="t.id"
                    class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm"
                >
                    <span>
                        {{ t.type_label }}
                        <span class="ml-2 text-xs text-muted-foreground">{{
                            formatKstDate(t.created_at)
                        }}</span>
                    </span>
                    <span
                        class="font-medium tabular-nums"
                        :class="t.amount >= 0 ? 'text-primary' : 'text-red-600'"
                        >{{ t.amount >= 0 ? '+' : ''
                        }}{{ t.amount.toLocaleString() }}P</span
                    >
                </li>
            </ul>
            <p
                v-else
                class="rounded-lg border border-dashed py-8 text-center text-sm text-muted-foreground"
            >
                아직 포인트 내역이 없어요. 출석하면 포인트를 받을 수 있어요.
            </p>
        </section>
    </div>
</template>
