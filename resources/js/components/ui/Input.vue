<script setup lang="ts">
import { cn } from '@/lib/utils';

const model = defineModel<string | number | null>();

withDefaults(
    defineProps<{
        id?: string;
        name?: string;
        type?: string;
        autocomplete?: string;
        placeholder?: string;
        class?: string;
        required?: boolean;
        defaultValue?: string;
        invalid?: boolean;
        describedBy?: string;
    }>(),
    {
        id: undefined,
        name: undefined,
        type: 'text',
        autocomplete: undefined,
        placeholder: undefined,
        class: '',
        required: false,
        defaultValue: undefined,
        invalid: false,
        describedBy: undefined,
    },
);
</script>

<template>
    <input
        :id="$props.id"
        :value="model ?? $props.defaultValue"
        :name="$props.name"
        :type="$props.type"
        :autocomplete="$props.autocomplete"
        :placeholder="$props.placeholder"
        :required="$props.required"
        :aria-invalid="$props.invalid ? 'true' : undefined"
        :aria-describedby="$props.describedBy"
        :class="
            cn(
                'h-11 w-full rounded-lg border bg-surface-container-lowest px-3 text-sm text-on-surface outline-none transition placeholder:text-on-surface-variant/50 focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20',
                $props.invalid
                    ? 'border-error-red focus-visible:border-error-red'
                    : 'border-outline-glass focus-visible:border-primary',
                $props.class,
            )
        "
        @input="model = ($event.target as HTMLInputElement).value"
    />
</template>
