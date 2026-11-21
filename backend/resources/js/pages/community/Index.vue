<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import BoardTabs from '@/components/community/BoardTabs.vue';
import PostRow from '@/components/community/PostRow.vue';
import { board as boardRoute } from '@/routes/community';
import type { Board, PostListItem } from '@/types/community';

// W-09 커뮤니티 홈: 게시판 목록과 전체 최신 글 (REQ-401)
defineProps<{ boards: Board[]; posts: PostListItem[] }>();
</script>

<template>
    <Head title="커뮤니티">
        <meta
            head-key="description"
            name="description"
            content="게이머들이 게임 이야기, 공략, 질문과 정보를 나누는 커뮤니티"
        />
    </Head>

    <div class="space-y-6">
        <h1 class="text-xl font-bold">커뮤니티</h1>
        <BoardTabs :boards="boards" />

        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <li v-for="b in boards" :key="b.slug">
                <Link
                    :href="boardRoute(b.slug)"
                    class="block h-full rounded-lg border p-4 hover:bg-muted/50"
                >
                    <p class="font-semibold">{{ b.name }}</p>
                    <p
                        v-if="b.description"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ b.description }}
                    </p>
                </Link>
            </li>
        </ul>

        <section class="space-y-2">
            <h2 class="font-semibold">최신 글</h2>
            <ul v-if="posts.length" class="divide-y">
                <PostRow
                    v-for="post in posts"
                    :key="post.id"
                    :post="post"
                    show-board
                />
            </ul>
            <p
                v-else
                class="rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground"
            >
                아직 글이 없어요. 첫 글을 남겨 주세요!
            </p>
        </section>
    </div>
</template>
