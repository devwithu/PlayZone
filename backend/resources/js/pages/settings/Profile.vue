<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteAccount from '@/components/DeleteAccount.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MarketingConsent from '@/components/settings/MarketingConsent.vue';
import ProfileImageForm from '@/components/settings/ProfileImageForm.vue';
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
    profile: {
        introduction: string | null;
        profile_image_url: string | null;
        marketing_agreed: boolean;
        marketing_agreed_at: string | null;
    };
    imageMaxMb: number;
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
            description="다른 회원에게 보이는 프로필 이미지, 닉네임, 소개를 바꿀 수 있습니다"
        />

        <ProfileImageForm
            :image-url="profile.profile_image_url"
            :nickname="user.nickname ?? '회원'"
            :max-mb="imageMaxMb"
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

            <div class="grid gap-2">
                <Label for="introduction">소개</Label>
                <textarea
                    id="introduction"
                    name="introduction"
                    rows="3"
                    maxlength="300"
                    :value="profile.introduction ?? ''"
                    placeholder="좋아하는 장르나 게임을 소개해 주세요 (300자 이내)"
                    class="w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError :message="errors.introduction" />
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

        <MarketingConsent
            class="border-t pt-6"
            :agreed="profile.marketing_agreed"
            :agreed-at="profile.marketing_agreed_at"
        />

        <DeleteAccount class="border-t pt-6" />
    </div>
</template>
