<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Pager from '@/components/mypage/Pager.vue';
import { Button } from '@/components/ui/button';
import { index as community } from '@/routes/community';
import { posts as postsRoute } from '@/routes/mypage';
import { show } from '@/routes/posts';
import type { MyPost } from '@/types/mypage';
import { formatKstDate, reviewStatusLabels } from '@/types/review';
import type { Pagination } from '@/types/review';

// W-17 내 글 탭 (REQ-603). 검토 중·숨김 글은 상태와 사유를 함께 보여 준다
defineProps<{ posts: { items: MyPost[]; pagination: Pagination } }>();

function pageUrl(p: number): string {
    return postsRoute({ query: p > 1 ? { page: p } : {} }).url;
}
</script>

<template>
    <Head title="내 글" />

    <div class="space-y-4">
        <p class="text-sm text-muted-foreground">
            글 {{ posts.pagination.total.toLocaleString() }}개
        </p>

        <div
            v-if="!posts.items.length"
            class="space-y-3 rounded-lg border border-dashed py-16 text-center text-sm text-muted-foreground"
        >
            <p>아직 작성한 글이 없어요.</p>
            <Button as-child variant="outline" size="sm">
                <Link :href="community()">커뮤니티 가기</Link>
            </Button>
        </div>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="p in posts.items"
                :key="p.id"
                class="space-y-1 p-4"
                :data-test="`my-post-${p.id}`"
            >
                <p
                    class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                >
                    <span>{{ p.board.name }}</span>
                    <span>{{ formatKstDate(p.created_at) }}</span>
                    <span
                        v-if="p.status !== 'published'"
                        class="rounded-full border border-amber-300 px-2 py-0.5 text-amber-700 dark:text-amber-300"
                        >{{ reviewStatusLabels[p.status] }}</span
                    >
                    <span v-if="!p.board.is_active">(운영이 끝난 게시판)</span>
                </p>
                <Link
                    v-if="p.board.is_active"
                    :href="show(p.id)"
                    class="block truncate font-medium hover:underline"
                    >{{ p.title }}</Link
                >
                <p v-else class="truncate font-medium">{{ p.title }}</p>
                <p class="text-xs text-muted-foreground">
                    조회 {{ p.view_count }} · 추천 {{ p.like_count }} · 댓글
                    {{ p.comment_count }}
                </p>
                <p
                    v-if="p.status !== 'published' && p.hidden_reason"
                    class="text-xs text-amber-700 dark:text-amber-300"
                >
                    사유: {{ p.hidden_reason }}
                </p>
            </li>
        </ul>

        <Pager :pagination="posts.pagination" :href="pageUrl" />
    </div>
</template>
