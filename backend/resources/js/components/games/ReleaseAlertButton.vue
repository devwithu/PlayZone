<script setup lang="ts">
import { Bell, BellRing } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useFavoriteActions } from '@/composables/useFavoriteActions';

// 🔔 출시 알림 토글. active가 null이면 비회원(누르면 로그인 화면)
const props = withDefaults(
    defineProps<{
        gameId: number;
        active: boolean | null;
        compact?: boolean;
    }>(),
    { compact: false },
);

const { toggleReleaseAlert } = useFavoriteActions();
</script>

<template>
    <Button
        type="button"
        :variant="active ? 'default' : 'outline'"
        :size="compact ? 'icon' : 'default'"
        :aria-pressed="active === true"
        :aria-label="active ? '출시 알림 끄기' : '출시 알림 켜기'"
        :title="active ? '출시 알림 끄기' : '출시 알림 켜기'"
        :data-test="`release-alert-${gameId}`"
        @click="toggleReleaseAlert(props.gameId, props.active)"
    >
        <BellRing v-if="active" class="size-4" />
        <Bell v-else class="size-4" />
        <span v-if="!compact">출시 알림</span>
    </Button>
</template>
