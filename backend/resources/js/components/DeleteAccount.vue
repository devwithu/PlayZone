<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AccountController from '@/actions/App/Http/Controllers/Settings/AccountController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

// W-18 설정 > 회원 탈퇴 (REQ-009): 안내를 확인하고 "탈퇴합니다"에 체크해야 진행한다
const open = ref(false);
const form = useForm({ confirm: false });

const submit = () => {
    form.submit(AccountController.destroy(), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <div class="space-y-6">
        <Heading
            variant="small"
            title="회원 탈퇴"
            description="탈퇴하면 계정을 되돌릴 수 없습니다"
        />

        <Dialog v-model:open="open">
            <DialogTrigger as-child>
                <Button variant="destructive" data-test="withdraw-button">
                    탈퇴하기
                </Button>
            </DialogTrigger>

            <DialogContent>
                <form class="space-y-6" @submit.prevent="submit">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>정말 탈퇴하시겠어요?</DialogTitle>
                        <DialogDescription as="div">
                            <ul class="list-disc space-y-1 pl-5 text-left">
                                <li>
                                    보유한 포인트는 모두 사라지고 되돌릴 수
                                    없습니다.
                                </li>
                                <li>
                                    같은 Google·Apple 계정으로는 30일 동안 다시
                                    가입할 수 없습니다.
                                </li>
                                <li>
                                    작성한 리뷰와 글은 "탈퇴한 회원" 이름으로
                                    남습니다.
                                </li>
                            </ul>
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label class="flex items-center gap-3 font-normal">
                            <Checkbox v-model="form.confirm" name="confirm" />
                            안내 내용을 확인했으며 탈퇴합니다
                        </Label>
                        <InputError :message="form.errors.confirm" />
                    </div>

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="secondary">
                                취소
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="!form.confirm || form.processing"
                            data-test="confirm-withdraw-button"
                        >
                            탈퇴합니다
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
