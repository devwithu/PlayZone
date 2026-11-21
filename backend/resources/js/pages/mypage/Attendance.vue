<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { CalendarCheck, ChevronLeft, ChevronRight, Flame } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { store } from '@/routes/attendance';
import { attendance as index } from '@/routes/mypage';

// W-17 마이페이지 출석 탭 (REQ-501~503)
const props = defineProps<{
    status: {
        date: string;
        attended: boolean;
        streak_days: number;
        points: number | null;
        next_bonus: {
            type: string;
            streak_days: number;
            days_left: number;
            points: number;
        } | null;
    };
    calendar: {
        month: string;
        count: number;
        days: { date: string; streak_days: number }[];
    };
    prevMonth: string;
    nextMonth: string | null;
}>();

const weekdays = ['일', '월', '화', '수', '목', '금', '토'];

const title = computed(() => {
    const [y, m] = props.calendar.month.split('-');

    return `${y}년 ${Number(m)}월`;
});

/** 달력 칸: 앞쪽 빈칸(null) + 그 달 날짜. 시간대 영향을 받지 않도록 UTC로 계산한다 */
const cells = computed(() => {
    const [y, m] = props.calendar.month.split('-').map(Number);
    const firstWeekday = new Date(Date.UTC(y, m - 1, 1)).getUTCDay();
    const lastDay = new Date(Date.UTC(y, m, 0)).getUTCDate();
    const attended = new Map(
        props.calendar.days.map((d) => [d.date, d.streak_days]),
    );
    const result: ({
        day: number;
        date: string;
        streak: number | null;
    } | null)[] = Array.from({ length: firstWeekday }, () => null);

    for (let day = 1; day <= lastDay; day++) {
        const date = `${props.calendar.month}-${String(day).padStart(2, '0')}`;
        result.push({ day, date, streak: attended.get(date) ?? null });
    }

    return result;
});

const bonusText = computed(() => {
    const bonus = props.status.next_bonus;

    if (!bonus) {
        return null;
    }

    return bonus.days_left === 1
        ? `다음 출석이 ${bonus.streak_days}일째! 보너스 +${bonus.points}P`
        : `${bonus.days_left}일 더 출석하면 ${bonus.streak_days}일 연속 보너스 +${bonus.points}P`;
});
</script>

<template>
    <Head title="출석 체크" />

    <div class="space-y-6">
        <section
            class="flex flex-col gap-4 rounded-lg border p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="space-y-1">
                <p class="flex items-center gap-2 text-2xl font-bold">
                    <Flame class="size-6 text-orange-500" />
                    {{ status.streak_days }}일 연속 출석
                </p>
                <p v-if="bonusText" class="text-sm text-muted-foreground">
                    {{ bonusText }}
                </p>
                <p class="text-xs text-muted-foreground">
                    하루 한 번, 한국 시간 0시에 초기화됩니다. 하루라도 빠지면
                    1일부터 다시 셉니다.
                </p>
            </div>

            <Form v-bind="store.form()" #default="{ processing }">
                <Button
                    type="submit"
                    size="lg"
                    class="w-full sm:w-auto"
                    :disabled="status.attended || processing"
                    data-test="attendance-button"
                >
                    <CalendarCheck class="size-4" />
                    <template v-if="status.attended">오늘 출석 완료</template>
                    <template v-else>
                        오늘 출석하기
                        <span v-if="status.points">+{{ status.points }}P</span>
                    </template>
                </Button>
            </Form>
        </section>

        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <Button variant="ghost" size="icon" as-child>
                    <Link
                        :href="index({ query: { month: prevMonth } })"
                        aria-label="이전 달"
                        preserve-scroll
                    >
                        <ChevronLeft class="size-4" />
                    </Link>
                </Button>
                <h2 class="font-semibold">
                    {{ title }}
                    <span class="text-sm font-normal text-muted-foreground">
                        {{ calendar.count }}일 출석
                    </span>
                </h2>
                <Button v-if="nextMonth" variant="ghost" size="icon" as-child>
                    <Link
                        :href="index({ query: { month: nextMonth } })"
                        aria-label="다음 달"
                        preserve-scroll
                    >
                        <ChevronRight class="size-4" />
                    </Link>
                </Button>
                <span v-else class="size-9" />
            </div>

            <div
                class="grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground"
            >
                <span v-for="w in weekdays" :key="w" class="py-1">{{ w }}</span>
            </div>
            <ol class="grid grid-cols-7 gap-1">
                <li
                    v-for="(cell, i) in cells"
                    :key="cell?.date ?? `blank-${i}`"
                    class="aspect-square"
                >
                    <div
                        v-if="cell"
                        class="flex size-full flex-col items-center justify-center rounded-md border text-sm"
                        :class="[
                            cell.streak
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'text-muted-foreground',
                            cell.date === status.date && !cell.streak
                                ? 'border-primary'
                                : '',
                        ]"
                        :title="
                            cell.streak ? `${cell.streak}일 연속` : undefined
                        "
                    >
                        <span>{{ cell.day }}</span>
                        <span
                            v-if="cell.streak === 7 || cell.streak === 30"
                            class="text-[10px] leading-none"
                            >🎁</span
                        >
                    </div>
                </li>
            </ol>
        </section>
    </div>
</template>
