<script setup lang="ts">
import WebForm from '@/components/ui/WebForm.vue';

import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { fieldError } from '@/composables/useFieldError';

defineProps<{
    email: string;
    token: string;
}>();

const { t } = useI18n();

useBoundLocale();
</script>

<template>
    <AuthLayout
        :title="t('auth.reset.title')"
        :subtitle="t('auth.reset.subtitle')"
    >
        <WebForm
            v-slot="{ errors, processing }"
            action="/reset-password"
            :reset-on-error="['password']"
            class="space-y-5"
        >
            <div class="space-y-2">
                <Label for="email">{{ t('auth.reset.labels.email') }}</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    :default-value="email"
                    :invalid="fieldError(errors, 'email', 'reset').invalid"
                    :described-by="
                        fieldError(errors, 'email', 'reset').describedBy
                    "
                    required
                />
                <FieldError v-bind="fieldError(errors, 'email', 'reset')" />
            </div>

            <input type="hidden" name="token" :value="token" />
            <FieldError v-bind="fieldError(errors, 'token', 'reset')" />

            <div class="space-y-2">
                <Label for="password">{{
                    t('auth.reset.labels.new_password')
                }}</Label>
                <Input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    :invalid="fieldError(errors, 'password', 'reset').invalid"
                    :described-by="
                        fieldError(errors, 'password', 'reset').describedBy
                    "
                    required
                />
                <FieldError v-bind="fieldError(errors, 'password', 'reset')" />
            </div>

            <Button type="submit" class="w-full" :disabled="processing">{{
                t('auth.reset.submit')
            }}</Button>
        </WebForm>
    </AuthLayout>
</template>
