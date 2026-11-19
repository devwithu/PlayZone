<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { nicknameCheck, store } from '@/routes/signup';

// W-03 가입 완료: 만 14세 확인, 약관 동의, 닉네임 (REQ-003, REQ-004)
defineOptions({
    layout: {
        title: '가입을 완료해 주세요',
        description: '닉네임을 정하고 약관에 동의하면 가입이 끝납니다.',
    },
});

const form = useForm({
    nickname: '',
    age_confirmed: false,
    terms: false,
    privacy: false,
    marketing: false,
});

const requiredKeys = ['age_confirmed', 'terms', 'privacy'] as const;
const allAgreed = computed({
    get: () => requiredKeys.every((key) => form[key]) && form.marketing,
    set: (value: boolean) => {
        requiredKeys.forEach((key) => (form[key] = value));
        form.marketing = value;
    },
});

type NicknameState = { available: boolean; message: string } | null;
const nicknameState = ref<NicknameState>(null);
const checking = ref(false);

const checkNickname = useDebounceFn(async (nickname: string) => {
    if (nickname.length < 2) {
        nicknameState.value = null;
        return;
    }
    checking.value = true;
    try {
        const response = await fetch(
            nicknameCheck({ query: { nickname } }).url,
            { headers: { Accept: 'application/json' } },
        );
        nicknameState.value = (await response.json()) as NicknameState;
    } finally {
        checking.value = false;
    }
}, 400);

watch(
    () => form.nickname,
    (nickname) => {
        nicknameState.value = null;
        void checkNickname(nickname);
    },
);

const canSubmit = computed(
    () =>
        requiredKeys.every((key) => form[key]) &&
        nicknameState.value?.available === true &&
        !form.processing,
);

const submit = () => form.submit(store());
</script>

<template>
    <Head title="가입 완료" />

    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="nickname">닉네임</Label>
            <Input
                id="nickname"
                v-model="form.nickname"
                name="nickname"
                maxlength="12"
                autocomplete="nickname"
                placeholder="2~12자, 한글·영문·숫자·_"
                v-focus
            />
            <p
                v-if="checking || nicknameState"
                class="flex items-center gap-1 text-sm"
                :class="
                    nicknameState?.available
                        ? 'text-green-600'
                        : 'text-muted-foreground'
                "
            >
                <Spinner v-if="checking" />
                <span
                    v-else
                    :class="{ 'text-destructive': !nicknameState?.available }"
                >
                    {{ nicknameState?.message }}
                </span>
            </p>
            <InputError :message="form.errors.nickname" />
        </div>

        <div class="grid gap-3 rounded-lg border p-4">
            <Label class="flex items-center gap-3 font-semibold">
                <Checkbox v-model="allAgreed" />
                전체 동의
            </Label>
            <hr />
            <Label class="flex items-center gap-3 font-normal">
                <Checkbox v-model="form.age_confirmed" name="age_confirmed" />
                만 14세 이상입니다 (필수)
            </Label>
            <InputError :message="form.errors.age_confirmed" />
            <Label class="flex items-center gap-3 font-normal">
                <Checkbox v-model="form.terms" name="terms" />
                이용약관 동의 (필수)
            </Label>
            <InputError :message="form.errors.terms" />
            <Label class="flex items-center gap-3 font-normal">
                <Checkbox v-model="form.privacy" name="privacy" />
                개인정보 처리방침 동의 (필수)
            </Label>
            <InputError :message="form.errors.privacy" />
            <Label class="flex items-center gap-3 font-normal">
                <Checkbox v-model="form.marketing" name="marketing" />
                마케팅 정보 수신 동의 (선택)
            </Label>
        </div>

        <p
            v-if="!form.age_confirmed"
            class="-mt-3 text-xs text-muted-foreground"
        >
            만 14세 미만은 가입할 수 없습니다.
        </p>

        <Button
            type="submit"
            size="lg"
            :disabled="!canSubmit"
            data-test="complete-signup"
        >
            <Spinner v-if="form.processing" />
            가입 완료
        </Button>
    </form>
</template>
