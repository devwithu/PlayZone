<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { show } from '@/routes/posts';
import { formatKstDate } from '@/types/review';
import type { PostListItem } from '@/types/community';

// 게시글 목록 한 줄 (W-10): 제목 [댓글 수], 닉네임, 날짜, 조회, 추천
defineProps<{ post: PostListItem; showBoard?: boolean }>();
</script>

<template>
    <li class="flex flex-col gap-1 py-3 sm:flex-row sm:items-center sm:gap-4">
        <Link
            :href="show(post.id)"
            class="min-w-0 flex-1 truncate text-sm hover:underline"
            :data-test="`post-${post.id}`"
        >
            <span v-if="showBoard" class="mr-1.5 text-xs text-muted-foreground"
                >[{{ post.board.name }}]</span
            >
            {{ post.title }}
            <span
                v-if="post.comment_count"
                class="ml-1 text-xs font-medium text-primary"
                >[{{ post.comment_count }}]</span
            >
        </Link>
        <p class="flex shrink-0 gap-3 text-xs text-muted-foreground">
            <span class="max-w-28 truncate">{{ post.author.nickname }}</span>
            <span>{{ formatKstDate(post.created_at) }}</span>
            <span>조회 {{ post.view_count }}</span>
            <span>추천 {{ post.like_count }}</span>
        </p>
    </li>
</template>
