<script setup lang="ts">
import WebForm from '@/components/ui/WebForm.vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';

const { t } = useI18n();

useBoundLocale();
</script>
<template>
    <AppLayout :title="t('auth.verify.title')">
        <section
            class="max-w-xl rounded-2xl border border-outline-glass bg-surface-container-lowest p-6 shadow-sm"
        >
            <h1 class="mb-3 text-2xl font-semibold">
                {{ t('auth.verify.title') }}
            </h1>
            <p
                class="max-w-xl text-sm leading-relaxed text-on-surface-variant font-medium"
            >
                {{ t('auth.verify.description') }}
            </p>

            <WebForm action="/verify-email" v-slot="{ processing }">
                <Button type="submit" class="mt-5" :disabled="processing">{{
                    t('auth.verify.submit')
                }}</Button>
            </WebForm>
        </section>
    </AppLayout>
</template>
