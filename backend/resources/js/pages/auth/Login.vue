<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { redirect } from '@/routes/social';

// W-02 로그인: Google·Apple만 제공 (ADR-003)
defineOptions({
    layout: {
        title: '게임평가단에 오신 것을 환영합니다',
        description:
            '처음이면 다음 단계에서 약관 동의와 닉네임 설정을 진행합니다.',
    },
});

defineProps<{
    providers: { id: string; label: string }[];
    error?: string | null;
}>();
</script>

<template>
    <Head title="로그인" />

    <div
        v-if="error"
        role="alert"
        class="mb-4 rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
    >
        {{ error }}
    </div>

    <div class="flex flex-col gap-3">
        <!-- 소셜 로그인은 외부 사이트로 이동하므로 Inertia 방문이 아닌 일반 링크를 쓴다 -->
        <Button
            v-for="provider in providers"
            :key="provider.id"
            as="a"
            :href="redirect(provider.id).url"
            variant="outline"
            size="lg"
            class="w-full"
            :data-test="`login-${provider.id}`"
        >
            {{ provider.label }}로 계속하기
        </Button>
    </div>
</template>
