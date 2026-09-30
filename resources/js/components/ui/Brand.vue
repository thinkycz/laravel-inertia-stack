<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Layers } from '@lucide/vue';
import { useSharedProps } from '@/composables/useSharedProps';
withDefaults(defineProps<{ href?: string }>(), { href: '/dashboard' });
const { app } = useSharedProps();
</script>
<template>
    <Link
        :href="href"
        class="flex min-w-0 items-center gap-3 font-semibold tracking-tight"
    >
        <span
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary text-surface-container-lowest"
            ><Layers :size="19" aria-hidden="true"
        /></span>
        <span class="truncate text-sm">{{ app.name }}</span>
    </Link>
</template>
