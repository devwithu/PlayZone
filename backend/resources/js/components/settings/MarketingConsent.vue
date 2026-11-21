<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/profile/marketing';
import { formatKstDate } from '@/types/review';

// W-18 알림: 마케팅 정보 수신 동의 (REQ-607). 바꾸면 바로 저장한다
const props = defineProps<{ agreed: boolean; agreedAt: string | null }>();

const saving = ref(false);

function change(value: boolean | 'indeterminate') {
    router.visit(update(), {
        data: { agreed: value === true },
        preserveScroll: true,
        onStart: () => (saving.value = true),
        onFinish: () => (saving.value = false),
    });
}
</script>

<template>
    <div class="space-y-4">
        <Heading
            variant="small"
            title="알림"
            description="이벤트, 혜택 같은 마케팅 정보를 받을지 정합니다"
        />
        <div class="flex items-start gap-3">
            <Checkbox
                id="marketing"
                :model-value="props.agreed"
                :disabled="saving"
                data-test="marketing-toggle"
                @update:model-value="change"
            />
            <div class="grid gap-1">
                <Label for="marketing">마케팅 정보 수신 동의 (선택)</Label>
                <p class="text-xs text-muted-foreground">
                    <template v-if="props.agreed && props.agreedAt">
                        {{ formatKstDate(props.agreedAt) }}에 동의했습니다.
                    </template>
                    <template v-else>
                        동의하지 않아도 서비스 이용에는 제한이 없습니다.
                    </template>
                </p>
            </div>
        </div>
    </div>
</template>
