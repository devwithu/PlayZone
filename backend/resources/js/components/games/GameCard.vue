<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { show } from '@/routes/games';
import type { GameListItem } from '@/types/game';

defineProps<{
    game: GameListItem;
}>();
</script>

<template>
    <Link
        :href="show(game.slug)"
        class="group flex flex-col gap-2"
        :data-test="`game-card-${game.id}`"
    >
        <div
            class="relative aspect-[3/4] overflow-hidden rounded-lg border bg-muted"
        >
            <img
                v-if="game.cover_url"
                :src="game.cover_url"
                :alt="`${game.title} 표지`"
                loading="lazy"
                class="size-full object-cover transition-transform group-hover:scale-105"
            />
            <div
                v-else
                class="flex size-full items-center justify-center p-3 text-center text-sm text-muted-foreground"
            >
                {{ game.title }}
            </div>

            <!-- REQ-105: 공개 리뷰 3개 미만이면 평균 대신 "평가 부족" -->
            <span
                class="absolute top-2 left-2 rounded-md bg-background/90 px-2 py-0.5 text-xs font-semibold shadow-sm"
            >
                <template v-if="game.rating_avg !== null">
                    ★ {{ game.rating_avg.toFixed(1) }}
                </template>
                <template v-else-if="!game.is_reviewable">출시 예정</template>
                <template v-else>평가 부족</template>
            </span>
        </div>

        <div class="space-y-0.5">
            <p class="line-clamp-2 text-sm font-medium group-hover:underline">
                {{ game.title }}
            </p>
            <p class="text-xs text-muted-foreground">
                리뷰 {{ game.review_count.toLocaleString() }}
                <template v-if="game.primary_release">
                    · {{ game.primary_release.display_date }}
                </template>
            </p>
        </div>
    </Link>
</template>
