<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/games';
import { releaseStatusLabels } from '@/types/game';
import type { GameDetail } from '@/types/game';

// W-05 게임 상세. 첫 HTML(SSR)에 제목·설명·OG 이미지 메타 태그가 들어간다 (WEB-003)
// 점수 분포·세부 평가·리뷰 목록은 3단계, 관심 게임·출시 알림은 관심 게임 작업에서 붙인다
const props = defineProps<{
    game: GameDetail;
    meta: { description: string; image: string | null; url: string };
}>();

const credits = computed(() =>
    [
        props.game.developer ? `개발: ${props.game.developer}` : null,
        props.game.publisher ? `배급: ${props.game.publisher}` : null,
    ]
        .filter(Boolean)
        .join(' / '),
);
</script>

<template>
    <Head :title="game.title">
        <meta
            head-key="description"
            name="description"
            :content="meta.description"
        />
        <link head-key="canonical" rel="canonical" :href="meta.url" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:title" property="og:title" :content="game.title" />
        <meta
            head-key="og:description"
            property="og:description"
            :content="meta.description"
        />
        <meta head-key="og:url" property="og:url" :content="meta.url" />
        <meta
            v-if="meta.image"
            head-key="og:image"
            property="og:image"
            :content="meta.image"
        />
        <meta
            head-key="twitter:card"
            name="twitter:card"
            :content="meta.image ? 'summary_large_image' : 'summary'"
        />
    </Head>

    <article class="space-y-8">
        <nav class="text-sm text-muted-foreground">
            <Link :href="index()" class="hover:underline">게임</Link>
            <span class="mx-1">›</span>
            <span>{{ game.title }}</span>
        </nav>

        <header class="flex flex-col gap-6 sm:flex-row">
            <div
                class="aspect-[3/4] w-40 shrink-0 overflow-hidden rounded-lg border bg-muted sm:w-48"
            >
                <img
                    v-if="game.cover_url"
                    :src="game.cover_url"
                    :alt="`${game.title} 표지`"
                    class="size-full object-cover"
                />
            </div>

            <div class="space-y-3">
                <h1 class="text-2xl font-bold">{{ game.title }}</h1>
                <p class="text-sm text-muted-foreground">
                    <span v-if="game.genres.length">
                        {{ game.genres.map((g) => g.name).join(' · ') }}
                    </span>
                    <span v-if="game.genres.length && credits" class="mx-2"
                        >|</span
                    >
                    <span v-if="credits">{{ credits }}</span>
                </p>

                <!-- REQ-105: 공개 리뷰 3개 미만이면 "평가 부족 (리뷰 N개)" -->
                <div
                    class="inline-flex items-baseline gap-3 rounded-lg border px-4 py-3"
                    data-test="game-rating"
                >
                    <template v-if="game.rating_avg !== null">
                        <span class="text-2xl font-bold"
                            >★ {{ game.rating_avg.toFixed(1) }}</span
                        >
                        <span class="text-sm text-muted-foreground">
                            리뷰 {{ game.review_count.toLocaleString() }}
                        </span>
                    </template>
                    <span v-else class="text-sm text-muted-foreground">
                        평가 부족 (리뷰 {{ game.review_count }}개)
                    </span>
                </div>

                <p
                    v-if="!game.is_reviewable"
                    class="text-sm text-muted-foreground"
                >
                    출시 후 리뷰를 쓸 수 있어요.
                </p>
            </div>
        </header>

        <section v-if="game.release_dates.length" class="space-y-3">
            <h2 class="font-semibold">출시일</h2>
            <ul class="flex flex-wrap gap-2">
                <li
                    v-for="release in game.release_dates"
                    :key="release.platform.slug"
                    class="flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm"
                >
                    <span class="font-medium">{{ release.platform.name }}</span>
                    <span>{{ release.display_date }}</span>
                    <Badge
                        v-if="release.release_status !== 'released'"
                        variant="secondary"
                    >
                        {{ releaseStatusLabels[release.release_status] }}
                    </Badge>
                </li>
            </ul>
        </section>

        <section v-if="game.screenshot_urls.length" class="space-y-3">
            <h2 class="font-semibold">스크린샷</h2>
            <div class="flex snap-x gap-3 overflow-x-auto pb-2">
                <img
                    v-for="(url, i) in game.screenshot_urls"
                    :key="url"
                    :src="url"
                    :alt="`${game.title} 스크린샷 ${i + 1}`"
                    loading="lazy"
                    class="h-48 shrink-0 snap-start rounded-lg border object-cover sm:h-64"
                />
            </div>
        </section>

        <section v-if="game.description" class="space-y-3">
            <h2 class="font-semibold">게임 소개</h2>
            <p class="text-sm leading-relaxed whitespace-pre-line">
                {{ game.description }}
            </p>
        </section>

        <section v-if="game.platforms.length" class="space-y-3">
            <h2 class="font-semibold">플랫폼</h2>
            <div class="flex flex-wrap gap-2">
                <Badge
                    v-for="platform in game.platforms"
                    :key="platform.slug"
                    variant="outline"
                >
                    {{ platform.name }}
                </Badge>
            </div>
        </section>
    </article>
</template>
