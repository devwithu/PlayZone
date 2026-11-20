<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import ReleaseRow from '@/components/releases/ReleaseRow.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/releases';
import type { ReleaseScheduleEntry, Taxonomy } from '@/types/game';

// W-08 출시 일정 (목록 보기). 일 단위는 날짜별, 월 단위는 "N월 중", 분기·연 단위는 아래에 따로 묶는다 (REQ-203)
// 출시 알림 버튼은 관심 게임 작업에서 붙인다
const props = defineProps<{
    month: string;
    prevMonth: string;
    nextMonth: string;
    today: string;
    platform: string;
    entries: ReleaseScheduleEntry[];
    platforms: Taxonomy[];
}>();

const weekdays = ['일', '월', '화', '수', '목', '금', '토'];

/** 'YYYY-MM-DD' → '12/3 (목)'. 시간대 영향을 받지 않도록 UTC로 계산한다 */
function dayLabel(date: string): string {
    const [y, m, d] = date.split('-').map(Number);
    const weekday = new Date(Date.UTC(y, m - 1, d)).getUTCDay();

    return `${m}/${d} (${weekdays[weekday]})`;
}

const monthNumber = computed(() => Number(props.month.split('-')[1]));
const title = computed(() => {
    const [y, m] = props.month.split('-');

    return `${y}년 ${Number(m)}월 출시 일정`;
});

const dayGroups = computed(() => {
    const groups = new Map<string, ReleaseScheduleEntry[]>();

    for (const entry of props.entries) {
        if (entry.date_precision !== 'day' || !entry.release_date) {
            continue;
        }

        groups.set(entry.release_date, [
            ...(groups.get(entry.release_date) ?? []),
            entry,
        ]);
    }

    return [...groups.entries()].map(([date, items]) => ({ date, items }));
});

const monthEntries = computed(() =>
    props.entries.filter((e) => e.date_precision === 'month'),
);
const roughEntries = computed(() =>
    props.entries.filter(
        (e) => e.date_precision === 'quarter' || e.date_precision === 'year',
    ),
);

function query(month: string, platform: string): Record<string, string> {
    const result: Record<string, string> = { month };

    if (platform) {
        result.platform = platform;
    }

    return result;
}

function monthUrl(month: string): string {
    return index({ query: query(month, props.platform) }).url;
}

function changePlatform(platform: string) {
    router.get(index().url, query(props.month, platform), {
        preserveState: true,
    });
}
</script>

<template>
    <Head :title="title">
        <meta
            head-key="description"
            name="description"
            :content="`${title}. PC·콘솔·모바일 신작 게임의 플랫폼별 출시일을 확인하세요.`"
        />
    </Head>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-1">
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="monthUrl(prevMonth)" aria-label="이전 달">
                        <ChevronLeft class="size-4" />
                    </Link>
                </Button>
                <h1 class="min-w-40 text-center text-lg font-semibold">
                    {{ title }}
                </h1>
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="monthUrl(nextMonth)" aria-label="다음 달">
                        <ChevronRight class="size-4" />
                    </Link>
                </Button>
            </div>

            <select
                class="ml-auto h-9 rounded-md border bg-background px-2 text-sm"
                aria-label="플랫폼"
                :value="platform"
                @change="
                    changePlatform(($event.target as HTMLSelectElement).value)
                "
            >
                <option value="">전체 플랫폼</option>
                <option v-for="p in platforms" :key="p.slug" :value="p.slug">
                    {{ p.name }}
                </option>
            </select>
        </div>

        <div
            v-if="!entries.length"
            class="rounded-lg border border-dashed py-16 text-center text-sm text-muted-foreground"
        >
            이 달에 등록된 출시 일정이 없습니다.
        </div>

        <section
            v-if="dayGroups.length || monthEntries.length"
            class="divide-y rounded-lg border"
        >
            <div
                v-for="group in dayGroups"
                :key="group.date"
                class="flex flex-col gap-2 p-4 sm:flex-row sm:gap-6"
                :data-test="`release-day-${group.date}`"
            >
                <p
                    class="w-24 shrink-0 text-sm font-semibold"
                    :class="group.date === today ? 'text-primary' : ''"
                >
                    {{ dayLabel(group.date) }}
                    <span v-if="group.date === today" class="text-xs"
                        >오늘</span
                    >
                </p>
                <ul class="flex-1 space-y-3">
                    <li
                        v-for="entry in group.items"
                        :key="`${entry.game.id}-${entry.release_status}`"
                    >
                        <ReleaseRow :entry="entry" />
                    </li>
                </ul>
            </div>

            <div
                v-if="monthEntries.length"
                class="flex flex-col gap-2 p-4 sm:flex-row sm:gap-6"
            >
                <p class="w-24 shrink-0 text-sm font-semibold">
                    {{ monthNumber }}월 중
                </p>
                <ul class="flex-1 space-y-3">
                    <li v-for="entry in monthEntries" :key="entry.game.id">
                        <ReleaseRow :entry="entry" />
                    </li>
                </ul>
            </div>
        </section>

        <section v-if="roughEntries.length" class="space-y-3">
            <h2 class="font-semibold">분기·연 단위 출시 예정</h2>
            <ul class="space-y-3 rounded-lg border p-4">
                <li
                    v-for="entry in roughEntries"
                    :key="`${entry.game.id}-${entry.release_date}`"
                    class="flex items-center gap-3"
                >
                    <span class="w-24 shrink-0 text-sm text-muted-foreground">
                        {{ entry.display_date }}
                    </span>
                    <ReleaseRow :entry="entry" />
                </li>
            </ul>
        </section>
    </div>
</template>
