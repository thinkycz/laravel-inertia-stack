<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
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
        :title="t('auth.login.title')"
        :subtitle="t('auth.login.subtitle')"
    >
        <Form
            v-slot="{ errors, processing }"
            action="/login"
            method="post"
            class="space-y-5"
        >
            <div class="space-y-2">
                <Label for="email">{{ t('fields.email') }}</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    :invalid="fieldError(errors, 'email', 'login').invalid"
                    :described-by="
                        fieldError(errors, 'email', 'login').describedBy
                    "
                    required
                />
                <FieldError v-bind="fieldError(errors, 'email', 'login')" />
            </div>

            <div class="space-y-2">
                <Label for="password">{{ t('fields.password') }}</Label>
                <Input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    :invalid="fieldError(errors, 'password', 'login').invalid"
                    :described-by="
                        fieldError(errors, 'password', 'login').describedBy
                    "
                    required
                />
                <div class="flex items-start justify-between gap-3">
                    <FieldError
                        v-bind="fieldError(errors, 'password', 'login')"
                    />
                    <Link
                        href="/forgot-password"
                        class="shrink-0 text-xs font-semibold text-primary hover:text-primary-container"
                        >{{ t('auth.login.forgot_link') }}</Link
                    >
                </div>
            </div>

            <Button type="submit" class="w-full" :disabled="processing">{{
                t('auth.login.submit')
            }}</Button>
        </Form>

        <p class="mt-6 text-center text-xs font-medium text-on-surface-variant">
            {{ t('auth.login.register_link') }}
            <Link
                href="/register"
                class="ml-1 font-bold text-primary hover:text-primary-container"
                >{{ t('auth.register.title') }}</Link
            >
        </p>
    </AuthLayout>
</template>
