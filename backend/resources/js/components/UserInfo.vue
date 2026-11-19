<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

const props = defineProps<{
    user: User;
}>();

const { getInitials } = useInitials();

const displayName = computed(() => props.user.nickname ?? '회원');
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarFallback class="rounded-lg text-black dark:text-white">
            {{ getInitials(displayName) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ displayName }}</span>
        <span class="truncate text-xs text-muted-foreground">
            {{ user.point_balance.toLocaleString() }}P
        </span>
    </div>
</template>
