<script setup lang="ts">
import { nextTick, onScopeDispose, ref, watch } from 'vue';
const panel = ref<HTMLElement | null>(null);
let previousFocus: HTMLElement | null = null;
let blocked: Array<{ element: HTMLElement; inert: boolean }> = [];
const props = withDefaults(
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
function release(): void {
    for (const { element, inert } of blocked) element.inert = inert;
    blocked = [];
    previousFocus?.focus();
    previousFocus = null;
}
function focusable(): HTMLElement[] {
    return Array.from(
        panel.value?.querySelectorAll<HTMLElement>(
            'button:not([disabled]), a[href], input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex="0"]',
        ) ?? [],
    ).filter((element) => element.getClientRects().length > 0);
}
function keydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        emit('close');
    }
    if (event.key !== 'Tab') return;
    const elements = focusable();
    const first = elements[0] ?? panel.value;
    const last = elements[elements.length - 1] ?? panel.value;
    if (
        event.shiftKey &&
        (document.activeElement === first ||
            document.activeElement === panel.value)
    ) {
        event.preventDefault();
        last?.focus();
    } else if (
        !event.shiftKey &&
        (document.activeElement === last || !elements.length)
    ) {
        event.preventDefault();
        first?.focus();
    }
}
watch(
    () => props.open,
    async (open) => {
        if (!open) {
            release();
            return;
        }
        previousFocus =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
        await nextTick();
        if (!props.open) return;
        for (const element of Array.from(document.body.children)) {
            if (
                element instanceof HTMLElement &&
                !element.contains(panel.value)
            ) {
                blocked.push({ element, inert: element.inert });
                element.inert = true;
            }
        }
        (focusable()[0] ?? panel.value)?.focus();
    },
    { immediate: true },
);
onScopeDispose(release);
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
                ref="panel"
                tabindex="-1"
                @keydown="keydown"
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
