<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        action: string;
        errorBag?: string;
        resetOnSuccess?: string[];
        resetOnError?: string[];
    }>(),
    { errorBag: undefined, resetOnSuccess: () => [], resetOnError: () => [] },
);
const emit = defineEmits<{ success: [] }>();
const errors = ref<Record<string, string>>({});
const processing = ref(false);

function submit(event: Event): void {
    if (processing.value || !(event.currentTarget instanceof HTMLFormElement))
        return;
    const form = event.currentTarget;
    const reset = (names: string[]): void => {
        for (const name of names) {
            const field = form.elements.namedItem(name);
            if (field instanceof HTMLInputElement) {
                field.value = '';
                field.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    };
    processing.value = true;
    errors.value = {};
    router.post(props.action, Object.fromEntries(new FormData(form)), {
        preserveScroll: true,
        errorBag: props.errorBag ?? props.action.replaceAll('/', '_'),
        onError: (value) => {
            errors.value = value;
            reset(props.resetOnError);
        },
        onSuccess: () => {
            reset(props.resetOnSuccess);
            emit('success');
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <form @submit.prevent="submit" :aria-busy="processing">
        <slot :errors="errors" :processing="processing" />
    </form>
</template>
