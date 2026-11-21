<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Crown } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/rankings';
import type { Author } from '@/types/review';

// W-13 랭킹 (REQ-508): 주간 포인트·월간 리뷰 탭, 기간 이동, 1~3위 강조, 내 순위. 공개 화면이라 SSR로 그린다
type RankingType = 'points_weekly' | 'reviews_monthly';

const props = defineProps<{
    ranking: {
        type: RankingType;
        period: {
            start: string;
            end: string;
            label: string;
            is_current: boolean;
        };
        updated_at: string | null;
        items: { rank: number; user: Author; score: number }[];
        me: { rank: number | null; score: number } | null;
        prev_date: string | null;
        next_date: string | null;
    };
    types: { value: RankingType; label: string; unit: string }[];
}>();

const current = computed(() =>
    props.types.find((t) => t.value === props.ranking.type)!,
);
const podium = computed(() => props.ranking.items.filter((i) => i.rank <= 3));
const rest = computed(() => props.ranking.items.filter((i) => i.rank > 3));

const rule = computed(() =>
    props.ranking.type === 'points_weekly'
        ? '월요일 0시부터 일요일 24시까지 출석·리뷰·게시글·댓글·추천으로 받은 포인트를 셉니다. 가입·이벤트·관리자 지급 포인트는 빼고, 회수된 포인트는 깎습니다.'
        : '그 달에 쓴 리뷰 중 공개 상태인 리뷰 수를 셉니다.',
);

function url(type: RankingType, date?: string | null): string {
    const query: Record<string, string> = {};

    if (type !== 'points_weekly') {
        query.type = type;
    }

    if (date) {
        query.date = date;
    }

    return index({ query }).url;
}

/** UTC ISO → KST 'MM.DD HH:00' */
function updatedLabel(iso: string): string {
    const kst = new Date(new Date(iso).getTime() + 9 * 60 * 60 * 1000);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${pad(kst.getUTCMonth() + 1)}.${pad(kst.getUTCDate())} ${pad(kst.getUTCHours())}:${pad(kst.getUTCMinutes())}`;
}

const medal = ['', 'text-amber-500', 'text-slate-400', 'text-orange-700'];
</script>

<template>
    <Head :title="`${current.label} 랭킹`">
        <meta
            head-key="description"
            name="description"
            :content="`게임평가단 ${current.label} 랭킹 (${ranking.period.label}). ${rule}`"
        />
    </Head>

    <div class="mx-auto w-full max-w-3xl space-y-6 pb-20">
        <h1 class="text-xl font-bold">랭킹</h1>

        <nav class="flex gap-1 border-b" aria-label="랭킹 종류">
            <Link
                v-for="t in types"
                :key="t.value"
                :href="url(t.value)"
                class="border-b-2 px-3 py-2 text-sm"
                :class="
                    t.value === ranking.type
                        ? 'border-primary font-semibold'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                >{{ t.label }}</Link
            >
        </nav>

        <div class="flex items-center justify-between gap-2">
            <Button
                variant="ghost"
                size="icon"
                as-child
                :class="{ invisible: !ranking.prev_date }"
            >
                <Link
                    :href="url(ranking.type, ranking.prev_date)"
                    aria-label="이전 기간"
                    ><ChevronLeft class="size-4"
                /></Link>
            </Button>
            <div class="text-center">
                <p class="font-semibold">
                    {{ ranking.period.label }}
                    <span
                        v-if="ranking.period.is_current"
                        class="text-xs font-normal text-primary"
                        >진행 중</span
                    >
                </p>
                <p class="text-xs text-muted-foreground">
                    <template v-if="ranking.updated_at"
                        >{{ updatedLabel(ranking.updated_at) }} 기준 · </template
                    >매시 정각 집계
                </p>
            </div>
            <Button
                variant="ghost"
                size="icon"
                as-child
                :class="{ invisible: !ranking.next_date }"
            >
                <Link
                    :href="url(ranking.type, ranking.next_date)"
                    aria-label="다음 기간"
                    ><ChevronRight class="size-4"
                /></Link>
            </Button>
        </div>

        <div
            v-if="!ranking.items.length"
            class="rounded-lg border border-dashed py-16 text-center text-sm text-muted-foreground"
        >
            아직 집계된 순위가 없어요.
        </div>

        <template v-else>
            <ol class="grid gap-3 sm:grid-cols-3">
                <li
                    v-for="item in podium"
                    :key="`${item.rank}-${item.user.id}`"
                    class="flex flex-col items-center gap-1 rounded-lg border p-4 text-center"
                >
                    <Crown class="size-6" :class="medal[item.rank]" />
                    <p class="text-xs text-muted-foreground">
                        {{ item.rank }}위
                    </p>
                    <p class="max-w-full truncate font-semibold">
                        {{ item.user.nickname }}
                    </p>
                    <p
                        v-if="item.user.level"
                        class="text-xs text-muted-foreground"
                    >
                        {{ item.user.level.name }}
                    </p>
                    <p class="text-lg font-bold tabular-nums">
                        {{ item.score.toLocaleString() }}{{ current.unit }}
                    </p>
                </li>
            </ol>

            <ol v-if="rest.length" class="divide-y rounded-lg border">
                <li
                    v-for="item in rest"
                    :key="`${item.rank}-${item.user.id}`"
                    class="flex items-center gap-3 px-4 py-2.5 text-sm"
                >
                    <span class="w-10 text-right font-medium tabular-nums">{{
                        item.rank
                    }}</span>
                    <span class="min-w-0 flex-1 truncate">
                        {{ item.user.nickname }}
                        <span
                            v-if="item.user.level"
                            class="ml-1 text-xs text-muted-foreground"
                            >{{ item.user.level.name }}</span
                        >
                    </span>
                    <span class="font-medium tabular-nums"
                        >{{ item.score.toLocaleString()
                        }}{{ current.unit }}</span
                    >
                </li>
            </ol>
        </template>

        <p class="text-xs text-muted-foreground">
            {{ rule }} 같은 점수는 같은 순위입니다. 100위까지 보여 줍니다.
        </p>
    </div>

    <div
        v-if="ranking.me"
        class="fixed inset-x-0 bottom-0 border-t bg-background/95 backdrop-blur"
        data-test="my-rank"
    >
        <div
            class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3 text-sm"
        >
            <span class="font-medium">내 순위</span>
            <span v-if="ranking.me.rank">
                <b>{{ ranking.me.rank }}위</b> ·
                {{ ranking.me.score.toLocaleString() }}{{ current.unit }}
            </span>
            <span v-else class="text-muted-foreground">
                아직 순위에 없어요
            </span>
        </div>
    </div>
</template>
