<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Check,
    Mail,
    ShieldCheck,
    SlidersHorizontal,
    Sparkles,
} from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { useSharedProps } from '@/composables/useSharedProps';
const { t } = useI18n();
const { user, features } = useSharedProps();
</script>
<template>
    <AppLayout :title="t('overview.title')">
        <div class="space-y-9">
            <header class="flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="section-label mb-3">{{ t('shell.workspace') }}</p>
                    <h1
                        class="text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        {{ t('overview.welcome') }}
                    </h1>
                    <p class="mt-3 max-w-xl text-base text-on-surface-variant">
                        {{ t('overview.description') }}
                    </p>
                </div>
                <Link href="/settings" class="button-secondary"
                    >{{ t('overview.manage') }}<ArrowRight :size="16"
                /></Link>
            </header>
            <section
                v-if="!user?.email_verified_at"
                class="flex flex-col justify-between gap-5 rounded-xl border border-amber-300/60 bg-amber-50/50 p-5 sm:flex-row sm:items-center dark:bg-amber-400/5"
            >
                <div class="flex items-start gap-4">
                    <Mail
                        :size="21"
                        class="mt-1 shrink-0 text-amber-700 dark:text-amber-300"
                    />
                    <div>
                        <h2 class="font-semibold">
                            {{ t('overview.verify_title') }}
                        </h2>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            {{ t('overview.verify_description') }}
                        </p>
                    </div>
                </div>
                <Link href="/verify-email" class="button-secondary shrink-0"
                    >{{ t('auth.verify.title') }}<ArrowRight :size="15"
                /></Link>
            </section>
            <section class="panel overflow-hidden">
                <div
                    class="flex items-center justify-between gap-4 border-b px-6 py-5"
                >
                    <h2 class="font-semibold">{{ t('overview.account') }}</h2>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-surface-container-low px-2.5 py-1 text-xs text-on-surface-variant"
                        ><Check :size="13" />{{ t('overview.signed_in') }}</span
                    >
                </div>
                <dl class="grid gap-6 p-6 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-on-surface-variant">
                            {{ t('fields.email') }}
                        </dt>
                        <dd class="mt-2 break-all font-medium">
                            {{ user?.email }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-on-surface-variant">
                            {{ t('fields.locale') }}
                        </dt>
                        <dd class="mt-2 font-medium">
                            {{ t(`locale.${user?.locale ?? 'en'}`) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-on-surface-variant">
                            {{ t('overview.email_status') }}
                        </dt>
                        <dd
                            class="mt-2 font-medium"
                            :class="
                                user?.email_verified_at
                                    ? 'text-success'
                                    : 'text-on-surface-variant'
                            "
                        >
                            {{
                                t(
                                    user?.email_verified_at
                                        ? 'overview.verified'
                                        : 'overview.pending',
                                )
                            }}
                        </dd>
                    </div>
                </dl>
            </section>
            <section>
                <h2 class="mb-4 font-semibold">
                    {{ t('overview.shortcuts') }}
                </h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <Link
                        href="/settings#security"
                        class="panel group flex items-start gap-4 p-6 transition-colors hover:border-primary/40"
                        ><span class="rounded-lg bg-surface-container-low p-3"
                            ><ShieldCheck :size="21"
                        /></span>
                        <div class="flex-1">
                            <h3 class="font-semibold">
                                {{ t('settings.security') }}
                            </h3>
                            <p
                                class="mt-2 text-sm leading-relaxed text-on-surface-variant"
                            >
                                {{ t('overview.security_description') }}
                            </p>
                            <span
                                class="mt-5 inline-flex items-center gap-2 text-sm font-medium"
                                >{{ t('overview.review_security')
                                }}<ArrowRight :size="14"
                            /></span></div
                    ></Link>
                    <Link
                        href="/settings#appearance"
                        class="panel group flex items-start gap-4 p-6 transition-colors hover:border-primary/40"
                        ><span class="rounded-lg bg-surface-container-low p-3"
                            ><SlidersHorizontal :size="21"
                        /></span>
                        <div class="flex-1">
                            <h3 class="font-semibold">
                                {{ t('settings.appearance') }}
                            </h3>
                            <p
                                class="mt-2 text-sm leading-relaxed text-on-surface-variant"
                            >
                                {{ t('overview.appearance_description') }}
                            </p>
                            <span
                                class="mt-5 inline-flex items-center gap-2 text-sm font-medium"
                                >{{ t('overview.personalize')
                                }}<ArrowRight :size="14"
                            /></span></div
                    ></Link>
                </div>
            </section>
            <Link
                v-if="features.ai"
                href="/assistant"
                class="flex items-center gap-4 border-t pt-6 text-on-surface-variant hover:text-on-surface"
                ><Sparkles :size="19" /><span class="flex-1 text-sm">{{
                    t('overview.assistant_description')
                }}</span
                ><span
                    class="inline-flex items-center gap-2 text-sm font-medium"
                    >{{ t('fields.assistant') }}<ArrowRight :size="16" /></span
            ></Link>
        </div>
    </AppLayout>
</template>
