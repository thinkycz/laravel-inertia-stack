<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const model = defineModel<string | number | null>();

const props = withDefaults(
    defineProps<{
        id?: string;
        name?: string;
        autocomplete?: string;
        required?: boolean;
        class?: string;
        options: Array<{ value: string; label: string }>;
        placeholder?: string;
        defaultValue?: string;
        invalid?: boolean;
        describedBy?: string;
    }>(),
    {
        id: undefined,
        name: undefined,
        autocomplete: undefined,
        required: false,
        class: '',
        placeholder: undefined,
        defaultValue: undefined,
        invalid: false,
        describedBy: undefined,
    },
);

const selectId = computed(
    () => props.id ?? `select-${Math.random().toString(36).slice(2, 9)}`,
);
</script>

<template>
    <select
        :id="selectId"
        :value="model ?? props.defaultValue"
        :name="props.name"
        :autocomplete="props.autocomplete"
        :required="props.required"
        :aria-invalid="props.invalid ? 'true' : undefined"
        :aria-describedby="props.describedBy"
        :class="
            cn(
                'h-11 w-full rounded-lg border bg-surface-container-lowest px-3 text-sm text-on-surface outline-none transition focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20',
                props.invalid
                    ? 'border-error-red focus-visible:border-error-red'
                    : 'border-outline-glass focus-visible:border-primary',
                props.class,
            )
        "
        @change="model = ($event.target as HTMLSelectElement).value"
    >
        <option v-if="props.placeholder" value="">
            {{ props.placeholder }}
        </option>
        <option
            v-for="option in props.options"
            :key="option.value"
            :value="option.value"
            :selected="props.defaultValue === option.value"
        >
            {{ option.label }}
        </option>
    </select>
</template>
