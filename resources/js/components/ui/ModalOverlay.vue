<script setup lang="ts">
withDefaults(
    defineProps<{
        open: boolean;
        variant?: 'dialog' | 'drawer';
        labelledBy?: string;
        panelClass?: string;
    }>(),
    {
        variant: 'dialog',
        labelledBy: undefined,
        panelClass: '',
    },
);

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex bg-black/40"
            :class="
                variant === 'drawer'
                    ? 'justify-end'
                    : 'items-center justify-center p-4'
            "
            @click.self="emit('close')"
        >
            <div
                role="dialog"
                aria-modal="true"
                :aria-labelledby="labelledBy"
                :class="[
                    variant === 'drawer'
                        ? 'flex h-screen max-h-screen w-full max-w-md flex-col overflow-y-auto bg-white p-6 shadow-xl'
                        : 'max-h-[calc(100vh-2rem)] w-full max-w-md overflow-y-auto rounded-2xl border border-outline-glass bg-white p-5 shadow-xl',
                    panelClass,
                ]"
            >
                <slot />
            </div>
        </div>
    </Teleport>
</template>
