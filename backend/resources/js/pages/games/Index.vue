<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Search,
    SlidersHorizontal,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import GameCard from '@/components/games/GameCard.vue';
import GameFilters from '@/components/games/GameFilters.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { index } from '@/routes/games';
import type { GameListItem, Taxonomy } from '@/types/game';

// W-04 게임 목록·검색. 필터·정렬·검색어·페이지는 URL 쿼리에 담는다 (공유·뒤로가기 유지)
type Filters = {
    q: string;
    genre: string[];
    platform: string[];
    release_status: string;
    sort: string;
};

const props = defineProps<{
    games: {
        items: GameListItem[];
        pagination: { current_page: number; last_page: number; total: number };
    };
    filters: Filters;
    keywordError: string | null;
    genres: Taxonomy[];
    platforms: Taxonomy[];
}>();

const keyword = ref(props.filters.q);
watch(
    () => props.filters.q,
    (value) => (keyword.value = value),
);

const sortOptions = [
    { value: 'popular', label: '인기순' },
    { value: 'rating', label: '평점순' },
    { value: 'latest_release', label: '최근 출시순' },
    { value: 'title', label: '이름순' },
];

/** 비어 있는 값은 주소에서 뺀다 */
function query(filters: Filters, page = 1): Record<string, string | number> {
    const result: Record<string, string | number> = {};

    if (filters.q) result.q = filters.q;
    if (filters.genre.length) result.genre = filters.genre.join(',');
    if (filters.platform.length) result.platform = filters.platform.join(',');
    if (filters.release_status) result.release_status = filters.release_status;
    if (filters.sort && filters.sort !== 'popular') result.sort = filters.sort;
    if (page > 1) result.page = page;

    return result;
}

function visit(changes: Partial<Filters>, page = 1) {
    router.get(index().url, query({ ...props.filters, ...changes }, page), {
        preserveState: true,
    });
}

function toggle(list: string[], slug: string): string[] {
    return list.includes(slug)
        ? list.filter((s) => s !== slug)
        : [...list, slug];
}

function pageUrl(page: number): string {
    return index({ query: query(props.filters, page) }).url;
}

const title = computed(() =>
    props.filters.q ? `"${props.filters.q}" 검색 결과` : '게임',
);

/** 현재 페이지 앞뒤 2쪽만 보여 준다 */
const pages = computed(() => {
    const { current_page: current, last_page: last } = props.games.pagination;
    const start = Math.max(1, current - 2);
    const end = Math.min(last, current + 2);

    return Array.from({ length: end - start + 1 }, (_, i) => start + i);
});

const activeFilterCount = computed(
    () =>
        props.filters.genre.length +
        props.filters.platform.length +
        (props.filters.release_status ? 1 : 0),
);
</script>

<template>
    <Head :title="title">
        <meta
            head-key="description"
            name="description"
            content="장르·플랫폼별 게임 정보와 출시일, 게이머들의 평가를 찾아보세요."
        />
    </Head>

    <div class="space-y-6">
        <form
            class="flex flex-col gap-2 sm:flex-row sm:items-center"
            role="search"
            @submit.prevent="visit({ q: keyword.trim() })"
        >
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="keyword"
                    type="search"
                    name="q"
                    placeholder="게임 제목으로 검색"
                    aria-label="게임 검색"
                    class="pl-9"
                />
            </div>
            <Button type="submit">검색</Button>
        </form>
        <p
            v-if="keywordError"
            class="-mt-4 text-sm text-destructive"
            role="alert"
        >
            {{ keywordError }}
        </p>

        <div class="flex gap-8">
            <aside class="hidden w-48 shrink-0 lg:block">
                <GameFilters
                    :genres="genres"
                    :platforms="platforms"
                    :selected-genres="filters.genre"
                    :selected-platforms="filters.platform"
                    :release-status="filters.release_status"
                    @toggle-genre="
                        (slug) => visit({ genre: toggle(filters.genre, slug) })
                    "
                    @toggle-platform="
                        (slug) =>
                            visit({ platform: toggle(filters.platform, slug) })
                    "
                    @change-release="
                        (value) => visit({ release_status: value })
                    "
                />
            </aside>

            <section class="min-w-0 flex-1 space-y-4">
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-semibold">
                        {{ title }}
                        <span class="text-sm font-normal text-muted-foreground">
                            {{ games.pagination.total.toLocaleString() }}개
                        </span>
                    </h1>

                    <div class="ml-auto flex items-center gap-2">
                        <!-- 모바일: 필터는 시트로 -->
                        <Sheet>
                            <SheetTrigger :as-child="true">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="lg:hidden"
                                >
                                    <SlidersHorizontal class="size-4" />
                                    필터
                                    <span v-if="activeFilterCount">
                                        {{ activeFilterCount }}
                                    </span>
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="bottom"
                                class="max-h-[80vh] overflow-y-auto"
                            >
                                <SheetHeader>
                                    <SheetTitle>필터</SheetTitle>
                                </SheetHeader>
                                <div class="px-4 pb-6">
                                    <GameFilters
                                        :genres="genres"
                                        :platforms="platforms"
                                        :selected-genres="filters.genre"
                                        :selected-platforms="filters.platform"
                                        :release-status="filters.release_status"
                                        @toggle-genre="
                                            (slug) =>
                                                visit({
                                                    genre: toggle(
                                                        filters.genre,
                                                        slug,
                                                    ),
                                                })
                                        "
                                        @toggle-platform="
                                            (slug) =>
                                                visit({
                                                    platform: toggle(
                                                        filters.platform,
                                                        slug,
                                                    ),
                                                })
                                        "
                                        @change-release="
                                            (value) =>
                                                visit({ release_status: value })
                                        "
                                    />
                                </div>
                            </SheetContent>
                        </Sheet>

                        <select
                            class="h-9 rounded-md border bg-background px-2 text-sm"
                            aria-label="정렬"
                            :value="filters.sort"
                            @change="
                                visit({
                                    sort: ($event.target as HTMLSelectElement)
                                        .value,
                                })
                            "
                        >
                            <option
                                v-for="option in sortOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div
                    v-if="games.items.length"
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4"
                >
                    <GameCard
                        v-for="game in games.items"
                        :key="game.id"
                        :game="game"
                    />
                </div>
                <div
                    v-else
                    class="rounded-lg border border-dashed py-16 text-center text-sm text-muted-foreground"
                >
                    조건에 맞는 게임이 없습니다.
                </div>

                <nav
                    v-if="games.pagination.last_page > 1"
                    class="flex items-center justify-center gap-1 pt-4"
                    aria-label="페이지"
                >
                    <Button
                        v-if="games.pagination.current_page > 1"
                        variant="ghost"
                        size="icon"
                        as-child
                    >
                        <Link
                            :href="pageUrl(games.pagination.current_page - 1)"
                            aria-label="이전 페이지"
                        >
                            <ChevronLeft class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        v-for="p in pages"
                        :key="p"
                        :variant="
                            p === games.pagination.current_page
                                ? 'default'
                                : 'ghost'
                        "
                        size="icon"
                        as-child
                    >
                        <Link
                            :href="pageUrl(p)"
                            :aria-current="
                                p === games.pagination.current_page
                                    ? 'page'
                                    : undefined
                            "
                        >
                            {{ p }}
                        </Link>
                    </Button>
                    <Button
                        v-if="
                            games.pagination.current_page <
                            games.pagination.last_page
                        "
                        variant="ghost"
                        size="icon"
                        as-child
                    >
                        <Link
                            :href="pageUrl(games.pagination.current_page + 1)"
                            aria-label="다음 페이지"
                        >
                            <ChevronRight class="size-4" />
                        </Link>
                    </Button>
                </nav>
            </section>
        </div>
    </div>
</template>
