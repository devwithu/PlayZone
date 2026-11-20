<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ReleaseAlertButton from '@/components/games/ReleaseAlertButton.vue';
import { Badge } from '@/components/ui/badge';
import { show } from '@/routes/games';
import { releaseStatusLabels } from '@/types/game';
import type { ReleaseScheduleEntry } from '@/types/game';

// 출시 일정 한 줄: 표지, 제목(게임 상세 링크), 플랫폼, 출시 전 상태, 🔔 출시 알림 (REQ-204)
const props = defineProps<{
    entry: ReleaseScheduleEntry;
    /** 게임 ID => 내 출시 알림. 비회원이면 null */
    alerts: Record<string, boolean> | null;
}>();

const alertState = (): boolean | null =>
    props.alerts === null ? null : (props.alerts[props.entry.game.id] ?? false);
</script>

<template>
    <div class="flex items-center gap-3">
        <Link
            :href="show(entry.game.slug)"
            class="group flex min-w-0 flex-1 items-center gap-3"
        >
            <div
                class="h-14 w-10 shrink-0 overflow-hidden rounded border bg-muted"
            >
                <img
                    v-if="entry.game.cover_url"
                    :src="entry.game.cover_url"
                    :alt="`${entry.game.title} 표지`"
                    loading="lazy"
                    class="size-full object-cover"
                />
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-medium group-hover:underline">
                    {{ entry.game.title }}
                </p>
                <p
                    class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                >
                    {{ entry.platforms.map((p) => p.name).join(' · ') }}
                    <Badge
                        v-if="entry.release_status !== 'released'"
                        variant="secondary"
                    >
                        {{ releaseStatusLabels[entry.release_status] }}
                    </Badge>
                </p>
            </div>
        </Link>

        <!-- 이미 출시된 항목에는 알림 버튼이 필요 없다 -->
        <ReleaseAlertButton
            v-if="entry.release_status !== 'released'"
            :game-id="entry.game.id"
            :active="alertState()"
            compact
        />
    </div>
</template>
