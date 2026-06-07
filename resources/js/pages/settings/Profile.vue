<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import { useSharedProps } from '@/composables/useSharedProps';

type ProfileFields = {
    email: string;
    locale: string;
};

const { user, app } = useSharedProps();

const localeOptions = [
    { value: 'en', label: 'English' },
    { value: 'cs', label: 'Čeština' },
    { value: 'sk', label: 'Slovenčina' },
];
</script>

<template>
    <AppLayout title="Profile settings">
        <section
            class="max-w-xl rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
        >
            <Form
                v-slot="{ errors, processing }"
                action="/settings/profile"
                method="post"
                class="space-y-5"
            >
                <div class="space-y-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        autocomplete="email"
                        :default-value="user?.email ?? ''"
                        required
                    />
                    <FieldError
                        :message="
                            (
                                errors as ProfileFields extends object
                                    ? ProfileFields
                                    : never
                            )['email']
                        "
                    />
                </div>

                <div class="space-y-2">
                    <Label for="locale">Locale</Label>
                    <Select
                        id="locale"
                        name="locale"
                        :options="localeOptions"
                        :default-value="user?.locale ?? app.locale"
                        required
                    />
                    <FieldError
                        :message="
                            (
                                errors as ProfileFields extends object
                                    ? ProfileFields
                                    : never
                            )['locale']
                        "
                    />
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="processing"
                        >Save profile</Button
                    >
                    <Link
                        href="/settings/password"
                        class="text-sm font-medium text-blue-700"
                        >Change password</Link
                    >
                </div>
            </Form>
        </section>
    </AppLayout>
</template>
