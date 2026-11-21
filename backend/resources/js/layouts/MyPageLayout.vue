<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Settings } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import {
    attendance,
    favorites,
    index,
    points,
    posts,
    reviews,
} from '@/routes/mypage';
import { edit as settings } from '@/routes/profile';
import type { MyPageHeader } from '@/types/mypage';

// W-17 마이페이지 공통 머리(닉네임·등급·잔액)와 탭. 모든 탭 화면이 header prop을 받는다
const page = usePage<{ header: MyPageHeader }>();
const header = computed(() => page.props.header);
const { isCurrentUrl } = useCurrentUrl();

const tabs = [
    { title: '대시보드', href: index() },
    { title: '내 리뷰', href: reviews() },
    { title: '내 글', href: posts() },
    { title: '관심 게임', href: favorites() },
    { title: '포인트', href: points() },
    { title: '출석', href: attendance() },
];
</script>

<template>
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <section
            class="flex flex-col gap-3 rounded-lg border p-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-4">
                <img
                    v-if="header.profile_image_url"
                    :src="header.profile_image_url"
                    alt="프로필 이미지"
                    class="size-14 shrink-0 rounded-full border object-cover"
                />
                <div
                    v-else
                    class="flex size-14 shrink-0 items-center justify-center rounded-full border bg-muted text-lg font-bold text-muted-foreground"
                >
                    {{ header.nickname.slice(0, 1) }}
                </div>
                <div class="space-y-1">
                    <p class="flex items-center gap-2 text-lg font-bold">
                        {{ header.nickname }}
                        <span
                            class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-medium"
                        >
                            <img
                                v-if="header.level.icon_url"
                                :src="header.level.icon_url"
                                alt=""
                                class="size-4"
                            />
                            {{ header.level.name }}
                        </span>
                    </p>
                    <p class="text-sm text-muted-foreground">
                        잔액
                        <b class="text-foreground"
                            >{{ header.point_balance.toLocaleString() }}P</b
                        >
                        · 누적 {{ header.lifetime_points.toLocaleString() }}P
                        <template v-if="header.next_level">
                            · {{ header.next_level.name }}까지
                            {{
                                header.next_level.points_needed.toLocaleString()
                            }}P
                        </template>
                        <template v-else> · 최고 등급</template>
                    </p>
                    <p
                        v-if="header.introduction"
                        class="text-sm whitespace-pre-line"
                    >
                        {{ header.introduction }}
                    </p>
                </div>
            </div>
            <Button variant="outline" size="sm" as-child>
                <Link :href="settings()"><Settings class="size-4" /> 설정</Link>
            </Button>
        </section>

        <nav
            class="flex gap-1 overflow-x-auto border-b"
            aria-label="마이페이지"
        >
            <Link
                v-for="tab in tabs"
                :key="tab.href.url"
                :href="tab.href"
                class="shrink-0 border-b-2 px-3 py-2 text-sm"
                :class="
                    isCurrentUrl(tab.href)
                        ? 'border-primary font-semibold'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                >{{ tab.title }}</Link
            >
        </nav>

        <slot />
    </div>
</template>
