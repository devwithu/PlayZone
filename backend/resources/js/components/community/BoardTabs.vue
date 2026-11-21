<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { board as boardRoute, index } from '@/routes/community';
import type { Board } from '@/types/community';

// 게시판 탭 (W-10). current가 없으면 커뮤니티 홈(전체)
defineProps<{ boards: Board[]; current?: string }>();
</script>

<template>
    <nav class="flex gap-1 overflow-x-auto border-b" aria-label="게시판">
        <Link
            :href="index()"
            class="shrink-0 border-b-2 px-3 py-2 text-sm"
            :class="
                !current
                    ? 'border-primary font-semibold'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
            >전체</Link
        >
        <Link
            v-for="b in boards"
            :key="b.slug"
            :href="boardRoute(b.slug)"
            class="shrink-0 border-b-2 px-3 py-2 text-sm"
            :class="
                current === b.slug
                    ? 'border-primary font-semibold'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
            >{{ b.name }}</Link
        >
    </nav>
</template>
