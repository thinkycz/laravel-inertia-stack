<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
const props = withDefaults(
    defineProps<{
        open: boolean;
        variant?: 'dialog' | 'drawer';
        labelledBy?: string;
        panelClass?: string;
    }>(),
    { variant: 'dialog', labelledBy: undefined, panelClass: '' },
);
const emit = defineEmits<{ close: [] }>();
const panel = ref<HTMLElement | null>(null);
let previous: HTMLElement | null = null;
let scrollStyle = '';
let active = false;
const focusables = (): HTMLElement[] =>
    Array.from(
        panel.value?.querySelectorAll<HTMLElement>(
            'button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex="0"]',
        ) ?? [],
    ).filter((el) => el.getClientRects().length > 0);
function keydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        emit('close');
    }
    if (event.key === 'Tab') {
        const items = focusables();
        const first = items[0];
        const last = items.at(-1);
        if (!first) {
            event.preventDefault();
            panel.value?.focus();
        } else if (
            event.shiftKey &&
            (document.activeElement === first ||
                document.activeElement === panel.value)
        ) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
}
function restore(): void {
    if (!active) return;
    active = false;
    document.removeEventListener('keydown', keydown);
    document.body.style.overflow = scrollStyle;
    if (previous?.isConnected) previous.focus();
}
watch(
    () => props.open,
    async (open) => {
        if (open) {
            active = true;
            previous =
                document.activeElement instanceof HTMLElement
                    ? document.activeElement
                    : null;
            scrollStyle = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', keydown);
            await nextTick();
            (focusables()[0] ?? panel.value)?.focus();
        } else restore();
    },
    { immediate: true },
);
onBeforeUnmount(() => {
    if (props.open) restore();
});
</script>
<template>
    <Teleport to="body"
        ><div
            v-if="open"
            class="fixed inset-0 z-50 flex bg-black/45 backdrop-blur-sm"
            :class="
                variant === 'drawer'
                    ? 'justify-end'
                    : 'items-center justify-center p-4'
            "
            @click.self="emit('close')"
        >
            <div
                ref="panel"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="labelledBy"
                tabindex="-1"
                :class="[
                    variant === 'drawer'
                        ? 'flex h-svh w-full max-w-sm flex-col overflow-y-auto border-l bg-surface-container-lowest p-6'
                        : 'max-h-[calc(100svh-2rem)] w-full max-w-md overflow-y-auto rounded-xl border bg-surface-container-lowest p-6 shadow-xl',
                    panelClass,
                ]"
            >
                <slot />
            </div></div
    ></Teleport>
</template>
