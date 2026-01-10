<script setup lang="ts">
import { cn } from '@/lib/utils';
import { computed, type HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const classes = computed(() =>
    cn('w-full caption-bottom text-sm', props.class),
);
</script>

<template>
    <div class="relative w-full overflow-auto">
        <table :class="classes">
            <slot />
        </table>
    </div>
</template>
