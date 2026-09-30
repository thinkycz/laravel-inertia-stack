<script setup lang="ts">
import WebForm from '@/components/ui/WebForm.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';
import { fieldError } from '@/composables/useFieldError';

const { app } = useSharedProps();
const { t, te } = useI18n();

useBoundLocale();

const localeOptions = computed(() =>
    app.value.locales.map((value: string) => ({
        value,
        label: te(`locale.${value}`) ? (t(`locale.${value}`) as string) : value,
    })),
);
</script>

<template>
    <AuthLayout
        :title="t('auth.register.title')"
        :subtitle="t('auth.register.subtitle')"
    >
        <WebForm
            v-slot="{ errors, processing }"
            action="/register"
            :reset-on-error="['password', 'password_confirmation']"
            class="space-y-5"
        >
            <div class="space-y-2">
                <Label for="email">{{ t('fields.email') }}</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="email"
                    :invalid="fieldError(errors, 'email', 'register').invalid"
                    :described-by="
                        fieldError(errors, 'email', 'register').describedBy
                    "
                    required
                />
                <FieldError v-bind="fieldError(errors, 'email', 'register')" />
            </div>

            <div class="space-y-2">
                <Label for="password">{{ t('fields.password') }}</Label>
                <Input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="new-password"
                    :invalid="
                        fieldError(errors, 'password', 'register').invalid
                    "
                    :described-by="
                        fieldError(errors, 'password', 'register').describedBy
                    "
                    required
                />
                <FieldError
                    v-bind="fieldError(errors, 'password', 'register')"
                />
            </div>

            <div class="space-y-2">
                <Label for="password_confirmation">{{
                    t('fields.password_confirmation')
                }}</Label>
                <Input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    :invalid="
                        fieldError(errors, 'password_confirmation', 'register')
                            .invalid
                    "
                    :described-by="
                        fieldError(errors, 'password_confirmation', 'register')
                            .describedBy
                    "
                    required
                />
                <FieldError
                    v-bind="
                        fieldError(errors, 'password_confirmation', 'register')
                    "
                />
            </div>

            <div class="space-y-2">
                <Label for="locale">{{ t('fields.locale') }}</Label>
                <Select
                    id="locale"
                    name="locale"
                    :options="localeOptions"
                    :default-value="app.locale"
                    :invalid="fieldError(errors, 'locale', 'register').invalid"
                    :described-by="
                        fieldError(errors, 'locale', 'register').describedBy
                    "
                    required
                />
                <FieldError v-bind="fieldError(errors, 'locale', 'register')" />
            </div>

            <Button type="submit" class="w-full" :disabled="processing">{{
                t('auth.register.submit')
            }}</Button>
        </WebForm>

        <p class="mt-6 text-center text-sm font-medium text-on-surface-variant">
            {{ t('auth.register.login_link') }}
            <Link
                href="/login"
                class="ml-1 font-bold text-primary hover:text-primary-container"
                >{{ t('auth.login.title') }}</Link
            >
        </p>
    </AuthLayout>
</template>
