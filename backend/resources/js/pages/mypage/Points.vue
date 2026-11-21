<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import Pager from '@/components/mypage/Pager.vue';
import { points as pointsRoute } from '@/routes/mypage';
import type { PointTransaction } from '@/types/mypage';
import { formatKstDate } from '@/types/review';
import type { Pagination } from '@/types/review';

// W-17 포인트 탭 (REQ-505): 거래 내역 (날짜, 사유, +/-, 잔액)과 사유 필터
const props = defineProps<{
    transactions: { items: PointTransaction[]; pagination: Pagination };
    type: string | null;
    types: { value: string; label: string }[];
}>();

function url(type: string | null, page = 1): string {
    const query: Record<string, string | number> = {};

    if (type) {
        query.type = type;
    }

    if (page > 1) {
        query.page = page;
    }

    return pointsRoute({ query }).url;
}

function changeType(event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    router.visit(url(value || null), { preserveScroll: true });
}
</script>

<template>
    <Head title="포인트 내역" />

    <div class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <p class="text-sm text-muted-foreground">
                {{ transactions.pagination.total.toLocaleString() }}건
            </p>
            <select
                :value="type ?? ''"
                aria-label="사유"
                class="h-8 rounded-md border bg-transparent px-2 text-sm"
                @change="changeType"
            >
                <option value="">전체 사유</option>
                <option v-for="t in types" :key="t.value" :value="t.value">
                    {{ t.label }}
                </option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-xs text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium">날짜</th>
                        <th class="px-4 py-2 text-left font-medium">사유</th>
                        <th class="px-4 py-2 text-right font-medium">포인트</th>
                        <th class="px-4 py-2 text-right font-medium">잔액</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="t in transactions.items" :key="t.id">
                        <td
                            class="px-4 py-2 whitespace-nowrap text-muted-foreground"
                        >
                            {{ formatKstDate(t.created_at) }}
                        </td>
                        <td class="px-4 py-2">
                            {{ t.type_label }}
                            <span
                                v-if="t.description"
                                class="block text-xs text-muted-foreground"
                                >{{ t.description }}</span
                            >
                        </td>
                        <td
                            class="px-4 py-2 text-right font-medium whitespace-nowrap tabular-nums"
                            :class="
                                t.amount >= 0 ? 'text-primary' : 'text-red-600'
                            "
                        >
                            {{ t.amount >= 0 ? '+' : ''
                            }}{{ t.amount.toLocaleString() }}P
                        </td>
                        <td
                            class="px-4 py-2 text-right whitespace-nowrap tabular-nums"
                        >
                            {{ t.balance_after.toLocaleString() }}P
                        </td>
                    </tr>
                    <tr v-if="!transactions.items.length">
                        <td
                            colspan="4"
                            class="py-10 text-center text-muted-foreground"
                        >
                            내역이 없어요.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pager
            :pagination="transactions.pagination"
            :href="(p: number) => url(props.type, p)"
        />
    </div>
</template>
