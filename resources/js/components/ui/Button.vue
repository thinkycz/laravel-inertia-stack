<script setup lang="ts">
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        type?: 'button' | 'submit';
        class?: string;
        disabled?: boolean;
    }>(),
    {
        type: 'button',
        class: '',
        disabled: false,
    },
);
</script>

<template>
    <button
        :type="props.type"
        :disabled="props.disabled"
        :class="
            cn(
                'inline-flex min-h-11 items-center justify-center rounded-lg border border-transparent bg-primary px-4 text-sm font-medium text-surface-container-lowest transition-colors hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50 gap-2',
                props.class,
            )
        "
    >
        <slot />
    </button>
</template>
