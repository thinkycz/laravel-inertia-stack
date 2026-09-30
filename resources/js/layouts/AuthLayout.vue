<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Layers, ShieldCheck } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import Brand from '@/components/ui/Brand.vue';
import FlashAlerts from '@/components/ui/FlashAlerts.vue';
import { useAppearance } from '@/composables/useAppearance';
import { useSharedProps } from '@/composables/useSharedProps';
defineProps<{ title: string; subtitle: string }>();
const { t } = useI18n();
const { app } = useSharedProps();
useAppearance();
</script>
<template>
    <div class="grid min-h-svh lg:grid-cols-[.9fr_1.1fr]">
        <Head :title="`${title} · ${app.name}`" />
        <aside
            class="relative hidden flex-col justify-between overflow-hidden bg-[#202b3b] p-12 text-white lg:flex xl:p-16"
        >
            <div
                class="relative z-10 flex items-center gap-3 text-sm font-medium"
            >
                <Layers :size="24" />{{ app.name }}
            </div>
            <div class="relative z-10 max-w-md py-16">
                <div class="mb-8 h-px w-12 bg-white/40"></div>
                <h2
                    class="text-4xl font-medium leading-tight tracking-tight xl:text-5xl"
                >
                    {{ t('auth.workspace_title') }}
                </h2>
                <p
                    class="mt-6 max-w-sm text-base leading-relaxed text-slate-300"
                >
                    {{ t('auth.workspace_description') }}
                </p>
            </div>
            <p
                class="relative z-10 flex items-center gap-2 text-xs text-slate-300"
            >
                <ShieldCheck :size="16" />{{ t('auth.private_workspace') }}
            </p>
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-48 -right-48 h-[38rem] w-[38rem] rounded-full border border-white/10 p-16"
            >
                <div
                    class="h-full w-full rounded-full border border-white/10 p-16"
                >
                    <div
                        class="h-full w-full rounded-full border border-white/10"
                    ></div>
                </div>
            </div>
        </aside>
        <main
            class="flex min-h-svh flex-col items-center justify-center bg-surface-bg px-6 py-12 sm:px-12"
        >
            <div class="w-full max-w-sm">
                <div class="mb-12 lg:hidden"><Brand href="/login" /></div>
                <header class="mb-8">
                    <h1 class="text-3xl font-semibold tracking-tight">
                        {{ title }}
                    </h1>
                    <p
                        class="mt-3 text-sm leading-relaxed text-on-surface-variant"
                    >
                        {{ subtitle }}
                    </p>
                </header>
                <FlashAlerts /><slot />
            </div>
        </main>
    </div>
</template>
