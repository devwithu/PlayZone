<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { PenLine, Search } from '@lucide/vue';
import BoardTabs from '@/components/community/BoardTabs.vue';
import PostRow from '@/components/community/PostRow.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { board as boardRoute, write } from '@/routes/community';
import type { Board, PostListItem } from '@/types/community';
import type { Pagination } from '@/types/review';

// W-10 게시글 목록: 게시판 탭, 정렬(최신·추천), 검색, 글쓰기 (REQ-402). 가려진 글은 목록에서 빠진다
const props = defineProps<{
    boards: Board[];
    board: Board;
    posts: { items: PostListItem[]; pagination: Pagination };
    filters: { sort: 'latest' | 'likes'; q: string };
    keywordError: string | null;
}>();

function url(changes: { sort?: string; page?: number }): string {
    const query: Record<string, string | number> = {};
    const sort = changes.sort ?? props.filters.sort;

    if (sort !== 'latest') {
        query.sort = sort;
    }

    if (props.filters.q) {
        query.q = props.filters.q;
    }

    if (changes.page && changes.page > 1) {
        query.page = changes.page;
    }

    return boardRoute(props.board.slug, { query }).url;
}
</script>

<template>
    <Head :title="board.name">
        <meta
            v-if="board.description"
            head-key="description"
            name="description"
            :content="board.description"
        />
    </Head>

    <div class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <h1 class="text-xl font-bold">{{ board.name }}</h1>
            <Button size="sm" as-child data-test="write-post">
                <Link :href="write(board.slug)"
                    ><PenLine class="size-4" /> 글쓰기</Link
                >
            </Button>
        </div>
        <BoardTabs :boards="boards" :current="board.slug" />

        <div class="flex flex-wrap items-start justify-between gap-2">
            <nav class="flex gap-1" aria-label="정렬">
                <Button
                    v-for="[value, label] in [
                        ['latest', '최신순'],
                        ['likes', '추천순'],
                    ]"
                    :key="value"
                    :variant="filters.sort === value ? 'secondary' : 'ghost'"
                    size="sm"
                    as-child
                >
                    <Link :href="url({ sort: value })">{{ label }}</Link>
                </Button>
            </nav>
            <Form
                :action="boardRoute(board.slug).url"
                method="get"
                class="flex gap-1"
            >
                <input
                    v-if="filters.sort !== 'latest'"
                    type="hidden"
                    name="sort"
                    :value="filters.sort"
                />
                <div>
                    <Input
                        name="q"
                        :default-value="filters.q"
                        placeholder="제목·본문 검색"
                        class="h-8 w-48"
                    />
                    <InputError :message="keywordError ?? undefined" />
                </div>
                <Button
                    type="submit"
                    size="icon"
                    variant="outline"
                    class="size-8"
                    aria-label="검색"
                >
                    <Search class="size-4" />
                </Button>
            </Form>
        </div>

        <ul v-if="posts.items.length" class="divide-y border-y">
            <PostRow v-for="post in posts.items" :key="post.id" :post="post" />
        </ul>
        <p
            v-else
            class="rounded-lg border border-dashed py-10 text-center text-sm text-muted-foreground"
        >
            {{
                filters.q
                    ? '검색 결과가 없어요.'
                    : '아직 글이 없어요. 첫 글을 남겨 주세요!'
            }}
        </p>

        <nav
            v-if="posts.pagination.last_page > 1"
            class="flex flex-wrap justify-center gap-1"
            aria-label="페이지"
        >
            <Button
                v-for="p in posts.pagination.last_page"
                :key="p"
                :variant="
                    p === posts.pagination.current_page ? 'default' : 'ghost'
                "
                size="icon"
                as-child
            >
                <Link :href="url({ page: p })">{{ p }}</Link>
            </Button>
        </nav>
    </div>
</template>
