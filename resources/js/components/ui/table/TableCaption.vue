<script setup lang="ts">
import { cn } from '@/lib/utils';
import { computed, type HTMLAttributes } from 'vue';

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const classes = computed(() =>
    cn('mt-4 text-sm text-muted-foreground', props.class),
);
</script>

<template>
    <caption :class="classes">
        <slot />
    </caption>
</template>
