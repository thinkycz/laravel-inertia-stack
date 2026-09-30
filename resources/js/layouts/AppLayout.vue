<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    Settings,
    LogOut,
    Menu,
    ArrowUpRight,
    Sparkles,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Brand from '@/components/ui/Brand.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import FlashAlerts from '@/components/ui/FlashAlerts.vue';
import ModalOverlay from '@/components/ui/ModalOverlay.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useSharedProps } from '@/composables/useSharedProps';
import { useAppearance } from '@/composables/useAppearance';
defineProps<{ title: string }>();
const { auth, activeUrl, features, app } = useSharedProps();
const { t } = useI18n();
const mobileOpen = ref(false);
useBoundLocale();
useAppearance();
const navigation = computed(() => [
    {
        href: '/dashboard',
        label: t('overview.title'),
        icon: LayoutDashboard,
        active: activeUrl.value.startsWith('/dashboard'),
    },
    ...(features.value.ai
        ? [
              {
                  href: '/assistant',
                  label: t('fields.assistant'),
                  icon: Sparkles,
                  active: /^\/(assistant|conversations)/.test(activeUrl.value),
              },
          ]
        : []),
    {
        href: '/settings',
        label: t('nav.settings'),
        icon: Settings,
        active: activeUrl.value.startsWith('/settings'),
    },
]);
watch(activeUrl, () => {
    mobileOpen.value = false;
});
</script>
<template>
    <div class="min-h-svh bg-surface-bg">
        <Head :title="`${title} · ${app.name}`" />
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-surface-container-lowest focus:p-3"
            >{{ t('nav.skip_to_main') }}</a
        >
        <aside
            class="fixed inset-y-0 left-0 z-20 hidden w-64 flex-col border-r bg-surface-container lg:flex"
        >
            <div class="px-6 py-8"><Brand /></div>
            <p class="section-label px-6 pb-3">{{ t('shell.workspace') }}</p>
            <nav :aria-label="t('shell.navigation')" class="space-y-1 px-3">
                <Link
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    :aria-current="item.active ? 'page' : undefined"
                    class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors"
                    :class="
                        item.active
                            ? 'bg-surface-container-lowest text-on-surface shadow-sm ring-1 ring-outline-glass'
                            : 'text-on-surface-variant hover:bg-surface-container-low'
                    "
                >
                    <component
                        :is="item.icon"
                        :size="18"
                        aria-hidden="true"
                    />{{ item.label }}
                </Link>
            </nav>
            <div class="mt-auto border-t p-5">
                <Link
                    href="/settings"
                    class="group mb-4 flex min-w-0 items-center gap-3"
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border bg-surface-container-lowest text-xs font-semibold"
                        >{{ auth.user?.email.slice(0, 2).toUpperCase() }}</span
                    >
                    <span class="min-w-0 flex-1"
                        ><span class="block text-xs text-on-surface-variant">{{
                            t('shell.your_account')
                        }}</span
                        ><span class="block truncate text-sm font-medium">{{
                            auth.user?.email
                        }}</span></span
                    >
                    <ArrowUpRight
                        :size="15"
                        class="shrink-0 text-on-surface-variant"
                    />
                </Link>
                <button
                    class="flex min-h-11 w-full items-center gap-3 rounded-lg px-2 text-sm text-on-surface-variant hover:bg-surface-container-low"
                    @click="router.post('/logout')"
                >
                    <LogOut :size="16" />{{ t('nav.logout') }}
                </button>
            </div>
        </aside>
        <div class="lg:pl-64">
            <header
                class="flex h-16 items-center justify-between border-b bg-surface-container-lowest px-5 sm:px-8"
            >
                <div class="flex items-center gap-3">
                    <button
                        class="button-secondary min-h-11 min-w-11 !p-2 lg:hidden"
                        :aria-label="t('shell.open_navigation')"
                        @click="mobileOpen = true"
                    >
                        <Menu :size="18" /></button
                    ><span class="text-sm text-on-surface-variant">{{
                        title
                    }}</span>
                </div>
                <Link
                    href="/settings#profile"
                    class="max-w-[50%] truncate text-xs text-on-surface-variant hover:text-on-surface"
                    >{{ auth.user?.email }}</Link
                >
            </header>
            <main
                id="main-content"
                tabindex="-1"
                class="mx-auto w-full max-w-6xl px-5 py-8 outline-none sm:px-8 sm:py-12 lg:px-12"
            >
                <FlashAlerts /><slot />
            </main>
        </div>
        <ModalOverlay
            :open="mobileOpen"
            variant="drawer"
            labelled-by="mobile-nav-title"
            @close="mobileOpen = false"
        >
            <h2 id="mobile-nav-title" class="mb-6 text-lg font-semibold">
                {{ t('shell.navigation') }}
            </h2>
            <nav class="space-y-2">
                <Link
                    v-for="item in navigation"
                    :key="item.href"
                    :href="item.href"
                    class="flex min-h-12 items-center gap-3 rounded-lg px-3"
                    :class="
                        item.active
                            ? 'bg-surface-container-low font-semibold'
                            : ''
                    "
                    @click="mobileOpen = false"
                    ><component :is="item.icon" :size="18" />{{
                        item.label
                    }}</Link
                >
            </nav>
            <button
                class="button-secondary mt-auto"
                @click="router.post('/logout')"
            >
                {{ t('nav.logout') }}
            </button>
            <button class="button-secondary mt-3" @click="mobileOpen = false">
                {{ t('common.closed') }}
            </button>
        </ModalOverlay>
        <ConfirmDialog />
    </div>
</template>
