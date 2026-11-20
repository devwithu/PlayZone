<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Toaster } from '@/components/ui/sonner';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import { home, login } from '@/routes';
import { index as games } from '@/routes/games';

// 로그인 없이 보는 공개 화면(게임, 출시 일정 등)의 레이아웃. SSR 대상이므로 window·document를 쓰지 않는다
const page = usePage();
const user = computed(() => page.props.auth.user);

const navItems = [{ title: '게임', href: games().url }];

const isCurrent = (href: string) =>
    page.url === href ||
    page.url.startsWith(`${href}/`) ||
    page.url.startsWith(`${href}?`);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background">
        <header class="border-b">
            <div
                class="mx-auto flex h-16 w-full max-w-6xl items-center gap-6 px-4"
            >
                <Link :href="home()" class="flex items-center">
                    <AppLogo />
                </Link>

                <nav class="flex items-center gap-1">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-md px-3 py-2 text-sm font-medium transition-colors hover:bg-accent"
                        :class="
                            isCurrent(item.href)
                                ? 'text-foreground'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ item.title }}
                    </Link>
                </nav>

                <div class="ml-auto">
                    <DropdownMenu v-if="user">
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="rounded-full"
                                :aria-label="`${user.nickname ?? '회원'} 메뉴`"
                            >
                                <Avatar class="size-8">
                                    <AvatarFallback>
                                        {{
                                            getInitials(user.nickname ?? '회원')
                                        }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Button v-else as-child variant="outline" size="sm">
                        <Link :href="login()">로그인</Link>
                    </Button>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6">
            <slot />
        </main>

        <footer class="border-t py-6 text-center text-xs text-muted-foreground">
            © 게임평가단
        </footer>
        <Toaster />
    </div>
</template>
