<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Heart } from '@lucide/vue';
import ReleaseAlertButton from '@/components/games/ReleaseAlertButton.vue';
import { Button } from '@/components/ui/button';
import { useFavoriteActions } from '@/composables/useFavoriteActions';
import { index as games, show } from '@/routes/games';
import { favorites as favoritesRoute } from '@/routes/mypage';
import type { FavoriteGame } from '@/types/game';

// W-17 마이페이지 관심 게임 탭 (REQ-604, REQ-204)
defineProps<{
    favorites: {
        items: FavoriteGame[];
        pagination: { current_page: number; last_page: number; total: number };
    };
}>();

const { toggleFavorite } = useFavoriteActions();
</script>

<template>
    <Head title="관심 게임" />

    <div class="space-y-4">
        <p class="text-sm text-muted-foreground">
            관심 게임 {{ favorites.pagination.total.toLocaleString() }}개
        </p>

        <div
            v-if="!favorites.items.length"
            class="space-y-3 rounded-lg border border-dashed py-16 text-center text-sm text-muted-foreground"
        >
            <p>아직 관심 게임이 없습니다.</p>
            <Button as-child variant="outline" size="sm">
                <Link :href="games()">게임 둘러보기</Link>
            </Button>
        </div>

        <ul v-else class="divide-y rounded-lg border">
            <li
                v-for="item in favorites.items"
                :key="item.game.id"
                class="flex items-center gap-4 p-4"
            >
                <Link
                    :href="show(item.game.slug)"
                    class="group flex min-w-0 flex-1 items-center gap-3"
                >
                    <div
                        class="h-16 w-12 shrink-0 overflow-hidden rounded border bg-muted"
                    >
                        <img
                            v-if="item.game.cover_url"
                            :src="item.game.cover_url"
                            :alt="`${item.game.title} 표지`"
                            loading="lazy"
                            class="size-full object-cover"
                        />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-medium group-hover:underline">
                            {{ item.game.title }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="item.game.primary_release">
                                {{ item.game.primary_release.display_date }}
                                {{
                                    item.game.is_reviewable
                                        ? '출시'
                                        : '출시 예정'
                                }}
                            </template>
                            <template v-else>출시일 미정</template>
                        </p>
                    </div>
                </Link>

                <ReleaseAlertButton
                    :game-id="item.game.id"
                    :active="item.release_alert"
                    compact
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="관심 게임 해제"
                    title="관심 게임 해제"
                    :data-test="`unfavorite-${item.game.id}`"
                    @click="toggleFavorite(item.game.id, true)"
                >
                    <Heart class="size-4 fill-current text-red-500" />
                </Button>
            </li>
        </ul>

        <nav
            v-if="favorites.pagination.last_page > 1"
            class="flex justify-center gap-1"
            aria-label="페이지"
        >
            <Button
                v-for="p in favorites.pagination.last_page"
                :key="p"
                :variant="
                    p === favorites.pagination.current_page
                        ? 'default'
                        : 'ghost'
                "
                size="icon"
                as-child
            >
                <Link
                    :href="favoritesRoute({ query: p > 1 ? { page: p } : {} })"
                >
                    {{ p }}
                </Link>
            </Button>
        </nav>
    </div>
</template>
