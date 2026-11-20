<script setup lang="ts">
import type { Taxonomy } from '@/types/game';

// W-04 왼쪽 필터 (모바일에서는 시트 안에 같은 컴포넌트를 쓴다)
defineProps<{
    genres: Taxonomy[];
    platforms: Taxonomy[];
    selectedGenres: string[];
    selectedPlatforms: string[];
    releaseStatus: string;
}>();

const emit = defineEmits<{
    toggleGenre: [slug: string];
    togglePlatform: [slug: string];
    changeRelease: [value: string];
}>();

const releaseOptions = [
    { value: '', label: '전체' },
    { value: 'released', label: '출시됨' },
    { value: 'upcoming', label: '출시 예정' },
];
</script>

<template>
    <div class="space-y-6 text-sm">
        <fieldset class="space-y-2">
            <legend class="mb-2 font-semibold">출시 여부</legend>
            <label
                v-for="option in releaseOptions"
                :key="option.value"
                class="flex cursor-pointer items-center gap-2"
            >
                <input
                    type="radio"
                    name="release_status"
                    class="accent-primary"
                    :value="option.value"
                    :checked="releaseStatus === option.value"
                    @change="emit('changeRelease', option.value)"
                />
                {{ option.label }}
            </label>
        </fieldset>

        <fieldset class="space-y-2">
            <legend class="mb-2 font-semibold">장르</legend>
            <label
                v-for="genre in genres"
                :key="genre.slug"
                class="flex cursor-pointer items-center gap-2"
            >
                <input
                    type="checkbox"
                    class="accent-primary"
                    :checked="selectedGenres.includes(genre.slug)"
                    :data-test="`filter-genre-${genre.slug}`"
                    @change="emit('toggleGenre', genre.slug)"
                />
                {{ genre.name }}
            </label>
        </fieldset>

        <fieldset class="space-y-2">
            <legend class="mb-2 font-semibold">플랫폼</legend>
            <label
                v-for="platform in platforms"
                :key="platform.slug"
                class="flex cursor-pointer items-center gap-2"
            >
                <input
                    type="checkbox"
                    class="accent-primary"
                    :checked="selectedPlatforms.includes(platform.slug)"
                    @change="emit('togglePlatform', platform.slug)"
                />
                {{ platform.name }}
            </label>
        </fieldset>
    </div>
</template>
