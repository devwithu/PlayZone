<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ImagePlus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { destroy, update } from '@/routes/profile/image';

// W-18 프로필 이미지: 고르면 바로 올린다. jpg·png·webp, 5MB 이하 (REQ-602)
const props = defineProps<{
    imageUrl: string | null;
    nickname: string;
    maxMb: number;
}>();

const form = useForm<{ image: File | null }>({ image: null });
const input = ref<HTMLInputElement | null>(null);
const pickError = ref<string | null>(null);

function pick(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    (event.target as HTMLInputElement).value = '';
    pickError.value = null;

    if (!file) {
        return;
    }

    if (file.size > props.maxMb * 1024 * 1024) {
        pickError.value = `프로필 이미지는 ${props.maxMb}MB 이하여야 합니다.`;

        return;
    }

    form.image = file;
    form.post(update().url, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => form.reset(),
    });
}

function remove() {
    if (confirm('프로필 이미지를 지울까요?')) {
        router.visit(destroy(), { preserveScroll: true });
    }
}
</script>

<template>
    <div class="flex items-center gap-4">
        <img
            v-if="imageUrl"
            :src="imageUrl"
            alt="프로필 이미지"
            class="size-20 rounded-full border object-cover"
        />
        <div
            v-else
            class="flex size-20 items-center justify-center rounded-full border bg-muted text-2xl font-bold text-muted-foreground"
        >
            {{ nickname.slice(0, 1) }}
        </div>

        <div class="space-y-2">
            <div class="flex gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="form.processing"
                    data-test="profile-image-pick"
                    @click="input?.click()"
                >
                    <ImagePlus class="size-4" />
                    {{ imageUrl ? '바꾸기' : '이미지 올리기' }}
                </Button>
                <Button
                    v-if="imageUrl"
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="form.processing"
                    @click="remove"
                >
                    <Trash2 class="size-4" /> 지우기
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                jpg·png·webp, {{ maxMb }}MB 이하, 가로·세로 100px 이상
            </p>
            <InputError :message="pickError ?? form.errors.image" />
        </div>

        <input
            ref="input"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="hidden"
            @change="pick"
        />
    </div>
</template>
