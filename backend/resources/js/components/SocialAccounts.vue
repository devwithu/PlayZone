<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import SocialAccountController from '@/actions/App/Http/Controllers/Settings/SocialAccountController';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { link } from '@/routes/social-accounts';

// W-18 설정 > 로그인 연결 (REQ-008): 하나만 남으면 해제할 수 없다
export type SocialAccountItem = {
    id: string;
    label: string;
    connected: boolean;
};

const props = defineProps<{
    accounts: SocialAccountItem[];
    message?: { type: 'success' | 'error'; message: string } | null;
}>();

const connectedCount = computed(
    () => props.accounts.filter((account) => account.connected).length,
);
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="로그인 연결"
            description="Google·Apple 계정을 함께 연결하면 어느 쪽으로든 로그인할 수 있습니다"
        />

        <Alert
            v-if="message"
            :variant="message.type === 'error' ? 'destructive' : 'default'"
            data-test="social-account-message"
        >
            <AlertDescription>{{ message.message }}</AlertDescription>
        </Alert>

        <ul class="divide-y rounded-lg border">
            <li
                v-for="account in accounts"
                :key="account.id"
                class="flex items-center justify-between gap-4 px-4 py-3"
            >
                <div class="flex items-center gap-3">
                    <span class="font-medium">{{ account.label }}</span>
                    <Badge v-if="account.connected" variant="secondary">
                        연결됨
                    </Badge>
                </div>

                <Form
                    v-if="account.connected"
                    v-bind="SocialAccountController.destroy.form(account.id)"
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        :disabled="connectedCount <= 1 || processing"
                        :title="
                            connectedCount <= 1
                                ? '마지막 로그인 수단은 해제할 수 없습니다'
                                : undefined
                        "
                        :data-test="`unlink-${account.id}`"
                    >
                        해제
                    </Button>
                </Form>

                <!-- 연결은 외부 사이트로 이동하므로 Inertia 방문이 아닌 일반 링크를 쓴다 -->
                <Button
                    v-else
                    as="a"
                    :href="link(account.id).url"
                    size="sm"
                    :data-test="`link-${account.id}`"
                >
                    연결하기
                </Button>
            </li>
        </ul>
    </div>
</template>
