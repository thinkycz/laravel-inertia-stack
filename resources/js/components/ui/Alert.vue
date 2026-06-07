<script setup lang="ts">
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        variant?: 'success' | 'error' | 'info';
    }>(),
    {
        variant: 'info',
    },
);

const variants = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    error: 'border-red-200 bg-red-50 text-red-900',
    info: 'border-blue-200 bg-blue-50 text-blue-950',
};
</script>

<template>
    <div
        role="alert"
        :class="
            cn('rounded-md border px-4 py-3 text-sm', variants[props.variant])
        "
    >
        <slot />
    </div>
</template>
