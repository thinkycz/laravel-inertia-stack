<script setup lang="ts">
withDefaults(
    defineProps<{
        for?: string;
        required?: boolean;
    }>(),
    {
        for: undefined,
        required: false,
    },
);
</script>

<template>
    <label :for="$props.for" class="text-sm font-medium text-on-surface">
        <slot />
        <span v-if="required" class="ml-0.5 text-error-red" aria-hidden="true"
            >*</span
        >
    </label>
</template>
