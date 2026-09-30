<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import WebForm from '@/components/ui/WebForm.vue';
import ModalOverlay from '@/components/ui/ModalOverlay.vue';
import { Monitor, Sun, Moon, ShieldCheck } from '@lucide/vue';
import { useAppearance, type Appearance } from '@/composables/useAppearance';
import type { LoginSession } from '@/types';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import FieldError from '@/components/ui/FieldError.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import Select from '@/components/ui/Select.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';
import { fieldError } from '@/composables/useFieldError';

defineProps<{ sessions: LoginSession[] }>();
const { appearance, setAppearance } = useAppearance();
const sessionToRevoke = ref<LoginSession | null>(null);
const deleting = ref(false);
const modes: { value: Appearance; icon: typeof Monitor }[] = [
    { value: 'system', icon: Monitor },
    { value: 'light', icon: Sun },
    { value: 'dark', icon: Moon },
];
const { user, app } = useSharedProps();
const { t, te } = useI18n();
function sessionDescription(agent: string | null): string {
    if (!agent) return t('security.unknown_browser');
    const browser = /Edg\//.test(agent)
        ? 'Edge'
        : /Firefox\//.test(agent)
          ? 'Firefox'
          : /Chrome\//.test(agent)
            ? 'Chrome'
            : /Safari\//.test(agent)
              ? 'Safari'
              : t('security.unknown_browser');
    const device = /iPhone|iPad/.test(agent)
        ? 'iOS'
        : /Android/.test(agent)
          ? 'Android'
          : /Windows/.test(agent)
            ? 'Windows'
            : /Macintosh/.test(agent)
              ? 'macOS'
              : /Linux/.test(agent)
                ? 'Linux'
                : '';
    return device ? `${browser} · ${device}` : browser;
}

useBoundLocale();

const localeOptions = computed(() =>
    app.value.locales.map((value: string) => ({
        value,
        label: te(`locale.${value}`) ? (t(`locale.${value}`) as string) : value,
    })),
);
</script>

<template>
    <AppLayout :title="t('settings.title')">
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <header>
                <h1
                    class="font-heading text-3xl font-semibold tracking-tight text-on-surface"
                >
                    {{ t('settings.title') }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('settings.subtitle') }}
                </p>
            </header>
            <nav
                :aria-label="t('nav.settings')"
                class="flex gap-5 border-b pb-3 text-sm"
            >
                <Link href="/settings#profile">{{
                    t('settings.profile.title')
                }}</Link
                ><Link href="/settings#security">{{
                    t('settings.security')
                }}</Link
                ><Link href="/settings#appearance">{{
                    t('settings.appearance')
                }}</Link>
            </nav>

            <section id="profile" class="panel p-6 sm:p-8">
                <h2
                    class="font-heading mb-4 text-base font-bold text-on-surface"
                >
                    {{ t('settings.profile.title') }}
                </h2>
                <WebForm
                    v-slot="{ errors, processing }"
                    action="/settings/profile"
                    class="space-y-5"
                >
                    <div class="space-y-2">
                        <Label for="email">{{ t('fields.email') }}</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            autocomplete="email"
                            :default-value="user?.email ?? ''"
                            :invalid="
                                fieldError(errors, 'email', 'profile').invalid
                            "
                            :described-by="
                                fieldError(errors, 'email', 'profile')
                                    .describedBy
                            "
                            required
                        />
                        <FieldError
                            v-bind="fieldError(errors, 'email', 'profile')"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="locale">{{ t('fields.locale') }}</Label>
                        <Select
                            id="locale"
                            name="locale"
                            :options="localeOptions"
                            :default-value="user?.locale ?? app.locale"
                            :invalid="
                                fieldError(errors, 'locale', 'profile').invalid
                            "
                            :described-by="
                                fieldError(errors, 'locale', 'profile')
                                    .describedBy
                            "
                            required
                        />
                        <FieldError
                            v-bind="fieldError(errors, 'locale', 'profile')"
                        />
                    </div>

                    <div
                        class="flex items-center justify-end border-t border-outline-glass pt-4"
                    >
                        <Button type="submit" :disabled="processing">
                            {{ t('settings.profile.submit') }}
                        </Button>
                    </div>
                </WebForm>
            </section>

            <section id="security" class="panel p-6 sm:p-8">
                <h2
                    class="font-heading mb-4 text-base font-bold text-on-surface"
                >
                    {{ t('settings.password.title') }}
                </h2>
                <p class="mb-5 text-sm text-on-surface-variant">
                    {{ t('security.password_hint') }}
                </p>
                <WebForm
                    v-slot="{ errors, processing }"
                    action="/settings/password"
                    :reset-on-success="['password', 'new_password']"
                    class="space-y-5"
                >
                    <div class="space-y-2">
                        <Label for="password">{{
                            t('fields.current_password')
                        }}</Label>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            :invalid="
                                fieldError(errors, 'password', 'password')
                                    .invalid
                            "
                            :described-by="
                                fieldError(errors, 'password', 'password')
                                    .describedBy
                            "
                            required
                        />
                        <FieldError
                            v-bind="fieldError(errors, 'password', 'password')"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="new_password">{{
                            t('fields.new_password')
                        }}</Label>
                        <Input
                            id="new_password"
                            name="new_password"
                            type="password"
                            autocomplete="new-password"
                            :invalid="
                                fieldError(errors, 'new_password', 'password')
                                    .invalid
                            "
                            :described-by="
                                fieldError(errors, 'new_password', 'password')
                                    .describedBy
                            "
                            required
                        />
                        <FieldError
                            v-bind="
                                fieldError(errors, 'new_password', 'password')
                            "
                        />
                    </div>

                    <div
                        class="flex items-center justify-end border-t border-outline-glass pt-4"
                    >
                        <Button type="submit" :disabled="processing">
                            {{ t('settings.password.submit') }}
                        </Button>
                    </div>
                </WebForm>
            </section>
            <section class="panel p-6 sm:p-8">
                <h2 class="text-base font-semibold">
                    {{ t('security.sessions') }}
                </h2>
                <p class="mt-2 text-sm text-on-surface-variant">
                    {{ t('security.sessions_hint') }}
                </p>
                <ul class="mt-5 divide-y">
                    <li
                        v-for="session in sessions"
                        :key="session.id"
                        class="flex flex-wrap items-center gap-3 py-4"
                    >
                        <ShieldCheck
                            :size="20"
                            class="shrink-0 text-on-surface-variant"
                        />
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-medium"
                                :title="session.browser ?? undefined"
                            >
                                {{ sessionDescription(session.browser) }}
                            </p>
                            <p class="mt-1 text-xs text-on-surface-variant">
                                <span v-if="session.last_used_at"
                                    >{{ t('security.last_active') }}
                                    {{
                                        new Date(
                                            session.last_used_at,
                                        ).toLocaleString(user?.locale, {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        })
                                    }}
                                    ·
                                </span>
                                {{ t('security.expires') }}
                                {{
                                    session.expires_at
                                        ? new Date(
                                              session.expires_at,
                                          ).toLocaleDateString(user?.locale)
                                        : '—'
                                }}
                            </p>
                        </div>
                        <span
                            v-if="session.current"
                            class="rounded-full bg-success/10 px-3 py-1 text-xs font-medium text-success"
                            >{{ t('security.current') }}</span
                        ><button
                            v-else
                            class="button-secondary text-sm"
                            @click="sessionToRevoke = session"
                        >
                            {{ t('security.revoke') }}
                        </button>
                    </li>
                </ul>
                <p
                    v-if="sessions.length === 0"
                    class="mt-4 text-sm text-on-surface-variant"
                >
                    {{ t('security.no_sessions') }}
                </p>
            </section>
            <section id="appearance" class="panel p-6 sm:p-8">
                <h2 class="text-base font-semibold">
                    {{ t('settings.appearance') }}
                </h2>
                <p class="mt-2 text-sm text-on-surface-variant">
                    {{ t('appearance.description') }}
                </p>
                <div
                    class="mt-6 grid grid-cols-3 gap-3"
                    role="group"
                    :aria-label="t('settings.appearance')"
                >
                    <button
                        v-for="mode in modes"
                        :key="mode.value"
                        :aria-pressed="appearance === mode.value"
                        class="flex min-h-24 flex-col items-center justify-center gap-3 rounded-lg border text-sm font-medium transition-colors"
                        :class="
                            appearance === mode.value
                                ? 'border-primary bg-surface-container-low ring-1 ring-primary'
                                : 'hover:bg-surface-container-low'
                        "
                        @click="setAppearance(mode.value)"
                    >
                        <component :is="mode.icon" :size="21" />{{
                            t(`appearance.${mode.value}`)
                        }}
                    </button>
                </div>
            </section>
            <section class="rounded-xl border border-error-red/25 p-6 sm:p-8">
                <h2 class="font-semibold text-error-red">
                    {{ t('security.delete_account') }}
                </h2>
                <p class="mb-5 mt-2 max-w-xl text-sm text-on-surface-variant">
                    {{ t('security.delete_hint') }}
                </p>
                <button
                    class="button-secondary border-error-red/30 text-error-red"
                    @click="deleting = true"
                >
                    {{ t('security.delete_account') }}
                </button>
            </section>
        </div>
        <ModalOverlay
            :open="sessionToRevoke !== null"
            labelled-by="revoke-title"
            @close="sessionToRevoke = null"
            ><h2 id="revoke-title" class="text-lg font-semibold">
                {{ t('security.revoke') }}
            </h2>
            <p class="mb-5 mt-2 text-sm text-on-surface-variant">
                {{ t('security.confirm_password') }}
            </p>
            <WebForm
                v-if="sessionToRevoke"
                v-slot="{ errors, processing }"
                :action="`/settings/sessions/${sessionToRevoke.id}/revoke`"
                @success="sessionToRevoke = null"
                ><Label for="revoke-password">{{
                    t('fields.current_password')
                }}</Label
                ><Input
                    id="revoke-password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    :invalid="!!errors.password"
                    :described-by="
                        errors.password ? 'revoke-password-error' : undefined
                    "
                    class="mt-2"
                />
                <p
                    v-if="errors.password"
                    id="revoke-password-error"
                    class="mt-2 text-sm text-error-red"
                >
                    {{ errors.password }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="button-secondary"
                        @click="sessionToRevoke = null"
                    >
                        {{ t('common.cancel') }}</button
                    ><Button type="submit" :disabled="processing">{{
                        t('security.revoke')
                    }}</Button>
                </div></WebForm
            ></ModalOverlay
        >
        <ModalOverlay
            :open="deleting"
            labelled-by="delete-title"
            @close="deleting = false"
            ><h2 id="delete-title" class="text-lg font-semibold">
                {{ t('security.delete_account') }}
            </h2>
            <p class="mb-5 mt-2 text-sm text-on-surface-variant">
                {{ t('security.delete_hint') }}
            </p>
            <WebForm v-slot="{ errors, processing }" action="/settings/delete"
                ><Label for="delete-password">{{
                    t('fields.current_password')
                }}</Label
                ><Input
                    id="delete-password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                    :invalid="!!errors.password"
                    :described-by="
                        errors.password ? 'delete-password-error' : undefined
                    "
                    class="mt-2"
                />
                <p
                    v-if="errors.password"
                    id="delete-password-error"
                    class="mt-2 text-sm text-error-red"
                >
                    {{ errors.password }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="button-secondary"
                        @click="deleting = false"
                    >
                        {{ t('common.cancel') }}</button
                    ><Button
                        type="submit"
                        class="!bg-error-red !text-white"
                        :disabled="processing"
                        >{{ t('security.delete_account') }}</Button
                    >
                </div></WebForm
            ></ModalOverlay
        >
    </AppLayout>
</template>
