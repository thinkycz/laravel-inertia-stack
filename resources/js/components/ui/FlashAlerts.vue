<script setup lang="ts">
import { computed } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import { useSharedProps } from '@/composables/useSharedProps';

const { flashSuccess, flashError } = useSharedProps();

const successMessage = computed<string | null>(() => flashSuccess.value);
const errorMessage = computed<string | null>(() => flashError.value);
</script>

<template>
    <div aria-live="polite">
        <div v-if="successMessage" class="mb-4">
            <Alert variant="success">
                {{ successMessage }}
            </Alert>
        </div>
        <div v-if="errorMessage" class="mb-4">
            <Alert variant="error">
                {{ errorMessage }}
            </Alert>
        </div>
    </div>
</template>
