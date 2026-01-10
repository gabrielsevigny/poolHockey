<script setup lang="ts">
import { TabsRoot } from 'reka-ui';
import type { TabsRootProps } from 'reka-ui';

defineProps<TabsRootProps>();
</script>

<template>
    <TabsRoot v-bind="$props">
        <slot />
    </TabsRoot>
</template>
