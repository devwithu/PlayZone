<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { Pagination } from '@/types/review';

// 마이페이지 목록의 페이지 번호. href는 페이지 번호로 주소를 만든다
defineProps<{ pagination: Pagination; href: (page: number) => string }>();
</script>

<template>
    <nav
        v-if="pagination.last_page > 1"
        class="flex flex-wrap justify-center gap-1"
        aria-label="페이지"
    >
        <Button
            v-for="p in pagination.last_page"
            :key="p"
            :variant="p === pagination.current_page ? 'default' : 'ghost'"
            size="icon"
            as-child
        >
            <Link :href="href(p)" preserve-scroll>{{ p }}</Link>
        </Button>
    </nav>
</template>
