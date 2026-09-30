<script setup lang="ts">
import WebForm from '@/components/ui/WebForm.vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { fieldError } from '@/composables/useFieldError';

const { t } = useI18n();

useBoundLocale();
</script>

<template>
    <AuthLayout
        :title="t('auth.forgot.title')"
        :subtitle="t('auth.forgot.subtitle')"
    >
        <WebForm
            v-slot="{ errors, processing }"
            action="/forgot-password"
            class="space-y-5"
        >
            <div class="space-y-2">
                <Label for="email">{{ t('fields.email') }}</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    :invalid="fieldError(errors, 'email', 'forgot').invalid"
                    :described-by="
                        fieldError(errors, 'email', 'forgot').describedBy
                    "
                    required
                />
                <FieldError v-bind="fieldError(errors, 'email', 'forgot')" />
            </div>

            <Button type="submit" class="w-full" :disabled="processing">{{
                t('auth.forgot.submit')
            }}</Button>
        </WebForm>

        <p class="mt-6 text-center text-sm font-medium text-on-surface-variant">
            <Link
                href="/login"
                class="font-bold text-primary hover:text-primary-container"
                >{{ t('auth.login.back_link') }}</Link
            >
        </p>
    </AuthLayout>
</template>
