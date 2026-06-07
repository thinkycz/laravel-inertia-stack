<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

type PasswordFields = {
    password: string;
    new_password: string;
};
</script>

<template>
    <AppLayout title="Password settings">
        <section
            class="max-w-xl rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
        >
            <Form
                v-slot="{ errors, processing }"
                action="/settings/password"
                method="post"
                :reset-on-success="['password', 'new_password']"
                class="space-y-5"
            >
                <div class="space-y-2">
                    <Label for="password">Current password</Label>
                    <Input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                    />
                    <FieldError
                        :message="
                            (
                                errors as PasswordFields extends object
                                    ? PasswordFields
                                    : never
                            )['password']
                        "
                    />
                </div>

                <div class="space-y-2">
                    <Label for="new_password">New password</Label>
                    <Input
                        id="new_password"
                        name="new_password"
                        type="password"
                        autocomplete="new-password"
                        required
                    />
                    <FieldError
                        :message="
                            (
                                errors as PasswordFields extends object
                                    ? PasswordFields
                                    : never
                            )['new_password']
                        "
                    />
                </div>

                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="processing"
                        >Update password</Button
                    >
                    <Link
                        href="/settings/profile"
                        class="text-sm font-medium text-blue-700"
                        >Back to profile</Link
                    >
                </div>
            </Form>
        </section>
    </AppLayout>
</template>
