<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';

const form = useForm({});

function submit(): void {
    form.post('/verify-email');
}
</script>
<template>
    <AppLayout title="Verify email">
        <section
            class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
        >
            <p class="max-w-2xl text-sm text-gray-700">
                Email verification is powered by the core notification flow.
                Send a fresh verification message when the current user still
                needs to verify their address.
            </p>

            <Button class="mt-5" :disabled="form.processing" @click="submit"
                >Send verification email</Button
            >
        </section>
    </AppLayout>
</template>
