<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteAccount from '@/components/DeleteAccount.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SocialAccounts from '@/components/SocialAccounts.vue';
import type { SocialAccountItem } from '@/components/SocialAccounts.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: '프로필 설정',
                href: edit(),
            },
        ],
    },
});

defineProps<{
    socialAccounts: SocialAccountItem[];
    socialAccountMessage?: {
        type: 'success' | 'error';
        message: string;
    } | null;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="프로필 설정" />

    <h1 class="sr-only">프로필 설정</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="프로필"
            description="다른 회원에게 보이는 닉네임을 바꿀 수 있습니다"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="nickname">닉네임</Label>
                <Input
                    id="nickname"
                    class="mt-1 block w-full"
                    name="nickname"
                    :default-value="user.nickname ?? ''"
                    required
                    maxlength="12"
                    autocomplete="nickname"
                    placeholder="2~12자, 한글·영문·숫자·_"
                />
                <InputError class="mt-2" :message="errors.nickname" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-profile-button"
                >
                    저장
                </Button>
            </div>
        </Form>

        <SocialAccounts
            class="border-t pt-6"
            :accounts="socialAccounts"
            :message="socialAccountMessage"
        />

        <DeleteAccount class="border-t pt-6" />
    </div>
</template>
