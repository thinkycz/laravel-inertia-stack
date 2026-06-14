<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    Settings as SettingsIcon,
    LogOut,
    Plus,
    MessageSquare,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Brand from '@/components/ui/Brand.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import FlashAlerts from '@/components/ui/FlashAlerts.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { useSharedProps } from '@/composables/useSharedProps';
import { useActiveConversation } from '@/composables/useActiveConversation';

defineProps<{
    title: string;
}>();

const { auth, conversations, activeUrl } = useSharedProps();
const { t } = useI18n();
const { pendingConversationId } = useActiveConversation();
const mobileHistoryOpen = ref(false);

useBoundLocale();

const activeConversationId = computed(() => {
    // While a new conversation is streaming, use the pending ID so the sidebar
    // item is highlighted immediately without needing a router navigation.
    if (pendingConversationId.value) return pendingConversationId.value;
    const match = activeUrl.value.match(/^\/conversations\/([a-zA-Z0-9-]+)/);
    return match ? match[1] : null;
});

const currentTab = computed(() => {
    if (activeUrl.value.startsWith('/settings')) {
        return 'settings';
    }
    return 'dashboard';
});

const userInitials = computed(() => {
    const email = auth.value.user?.email ?? '';
    if (!email) return '';
    return email.substring(0, 2).toUpperCase();
});

const userLabel = computed(() => {
    const user = auth.value.user;
    if (!user) return t('fields.user');
    return user.email.split('@')[0];
});

function logout(): void {
    router.post('/logout');
}

async function deleteConversation(id: string): Promise<void> {
    const confirmDialog = useConfirmDialog();

    if (
        await confirmDialog.confirm(t('conversations.delete_confirm'))
    ) {
        router.delete(`/conversations/${id}`);
    }
}

function closeMobileHistory(): void {
    mobileHistoryOpen.value = false;
}
</script>

<template>
    <Head :title="title" />

    <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-xl focus:bg-primary focus:px-4 focus:py-2 focus:text-xs focus:font-bold focus:text-white"
    >
        {{ t('nav.skip_to_main') }}
    </a>

    <div
        class="flex h-screen flex-col overflow-hidden bg-surface-bg font-sans antialiased md:flex-row"
    >
        <!-- Desktop Persistent Sidebar -->
        <aside
            class="sticky top-0 z-20 hidden h-screen w-64 flex-col border-r border-outline-glass bg-surface-container px-4 py-6 text-left md:flex"
        >
            <!-- Brand App Header -->
            <div
                class="mb-8 flex cursor-default items-center gap-3 px-2 transition-all select-none"
            >
                <Brand href="/dashboard" />
            </div>

            <!-- Nav Links -->
            <nav class="flex-1 space-y-1.5 overflow-y-auto">
                <Link
                    href="/dashboard"
                    :class="[
                        'flex w-full cursor-pointer items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold transition-all',
                        currentTab === 'dashboard' && !activeConversationId
                            ? 'border-r-2 border-primary bg-surface-container-low font-bold text-primary shadow-[inset_0_1px_0_rgba(255,255,255,0.3)]'
                            : 'text-on-surface-variant hover:bg-surface-container-low',
                    ]"
                >
                    <Activity :size="16" />
                    {{ t('nav.dashboard') }}
                </Link>

                <Link
                    href="/dashboard"
                    class="flex w-full cursor-pointer items-center gap-3 rounded-xl px-3 py-2 text-xs font-semibold text-on-surface-variant transition-all hover:bg-surface-container-low"
                    :title="t('nav.new_chat')"
                >
                    <Plus :size="16" />
                    {{ t('nav.new_chat') }}
                </Link>

                <!-- Conversations History -->
                <div
                    v-if="conversations.length > 0"
                    class="mt-6 pt-4 border-t border-outline-glass"
                >
                    <p
                        class="px-3 mb-2 text-[10px] font-bold tracking-wider text-on-surface-variant uppercase opacity-75"
                    >
                        {{ t('nav.history') }}
                    </p>
                    <TransitionGroup name="list" tag="div" class="space-y-1">
                        <div
                            v-for="chat in conversations"
                            :key="chat.id"
                            class="group relative flex w-full items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold transition-all hover:bg-surface-container-low"
                            :class="[
                                activeConversationId === chat.id
                                    ? 'bg-surface-container-low font-bold text-primary border-r-2 border-primary'
                                    : 'text-on-surface-variant',
                            ]"
                        >
                            <Link
                                :href="`/conversations/${chat.id}`"
                                class="flex flex-1 items-center gap-3 truncate pr-6 text-left"
                            >
                                <MessageSquare :size="14" class="shrink-0" />
                                <span class="truncate">{{ chat.title }}</span>
                            </Link>
                            <button
                                @click.stop="deleteConversation(chat.id)"
                                class="absolute right-2 hidden cursor-pointer rounded-lg p-1 text-on-surface-variant hover:bg-error-red/10 hover:text-error-red group-hover:block"
                                :title="t('nav.delete_chat')"
                            >
                                <Trash2 :size="12" />
                            </button>
                        </div>
                    </TransitionGroup>
                </div>
            </nav>

            <!-- Footer: User Identity + Quick Actions -->
            <div
                class="flex items-center justify-between gap-2 border-t border-outline-glass pt-4 px-2"
            >
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <div
                        aria-hidden="true"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-outline-glass bg-surface-container-low font-heading text-xs font-bold text-primary"
                    >
                        {{ userInitials }}
                    </div>
                    <div class="min-w-0 overflow-hidden">
                        <p
                            class="truncate text-xs font-semibold text-on-surface"
                        >
                            {{ userLabel }}
                        </p>
                        <p
                            class="truncate text-[9px] text-on-surface-variant opacity-85 font-medium"
                        >
                            {{ auth.user ? auth.user.email : '' }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    <Link
                        href="/settings"
                        :class="[
                            'cursor-pointer rounded-lg p-1.5 transition-all',
                            currentTab === 'settings'
                                ? 'bg-surface-container-low text-primary'
                                : 'text-on-surface-variant hover:bg-surface-container-low hover:text-primary',
                        ]"
                        :title="t('nav.settings')"
                        :aria-label="t('nav.settings')"
                    >
                        <SettingsIcon :size="14" />
                    </Link>
                    <button
                        @click="logout"
                        class="cursor-pointer rounded-lg p-1.5 text-on-surface-variant transition-all hover:bg-error-red/10 hover:text-error-red"
                        :title="t('nav.logout')"
                        :aria-label="t('nav.logout')"
                    >
                        <LogOut :size="14" />
                    </button>
                </div>
            </div>
        </aside>

        <!-- Mobile Top Navigation Header -->
        <header
            class="glass-panel sticky top-0 z-30 flex h-16 w-full items-center justify-between border-b border-outline-glass px-4 shadow-sm md:hidden"
        >
            <div class="flex items-center gap-2">
                <Brand href="/dashboard" />
            </div>

            <div class="flex items-center gap-1.5">
                <Link
                    href="/dashboard"
                    class="rounded-lg p-2 text-on-surface-variant transition-all"
                    :title="t('nav.new_chat')"
                    :aria-label="t('nav.new_chat')"
                    @click="closeMobileHistory"
                >
                    <Plus :size="16" />
                </Link>
                <button
                    type="button"
                    class="rounded-lg p-2 text-on-surface-variant transition-all"
                    :class="
                        mobileHistoryOpen
                            ? 'bg-surface-container-low text-primary'
                            : ''
                    "
                    :title="t('nav.history')"
                    :aria-label="t('nav.history')"
                    :aria-expanded="mobileHistoryOpen"
                    @click="mobileHistoryOpen = !mobileHistoryOpen"
                >
                    <MessageSquare :size="16" />
                </button>
                <Link
                    href="/dashboard"
                    :class="[
                        'rounded-lg p-2 transition-all',
                        currentTab === 'dashboard'
                            ? 'font-bold text-primary bg-surface-container-low'
                            : 'text-on-surface-variant',
                    ]"
                    @click="closeMobileHistory"
                >
                    <Activity :size="16" />
                </Link>
                <Link
                    href="/settings"
                    :class="[
                        'rounded-lg p-2 transition-all',
                        currentTab === 'settings'
                            ? 'font-bold text-primary bg-surface-container-low'
                            : 'text-on-surface-variant',
                    ]"
                    @click="closeMobileHistory"
                >
                    <SettingsIcon :size="16" />
                </Link>
                <button
                    @click="logout"
                    class="rounded-lg p-2 text-on-surface-variant transition-all hover:text-error-red"
                >
                    <LogOut :size="16" />
                </button>
            </div>
        </header>

        <div
            v-if="mobileHistoryOpen"
            class="glass-panel z-20 max-h-64 overflow-y-auto border-b border-outline-glass px-4 py-3 md:hidden"
        >
            <p
                class="mb-2 text-[10px] font-bold tracking-wider text-on-surface-variant uppercase opacity-75"
            >
                {{ t('nav.history') }}
            </p>
            <div v-if="conversations.length > 0" class="space-y-1">
                <div
                    v-for="chat in conversations"
                    :key="chat.id"
                    class="relative flex items-center justify-between rounded-xl px-3 py-2 text-xs font-semibold"
                    :class="[
                        activeConversationId === chat.id
                            ? 'bg-surface-container-low font-bold text-primary'
                            : 'text-on-surface-variant',
                    ]"
                >
                    <Link
                        :href="`/conversations/${chat.id}`"
                        class="flex flex-1 items-center gap-3 truncate pr-8 text-left"
                        @click="closeMobileHistory"
                    >
                        <MessageSquare :size="14" class="shrink-0" />
                        <span class="truncate">{{ chat.title }}</span>
                    </Link>
                    <button
                        type="button"
                        @click.stop="deleteConversation(chat.id)"
                        class="absolute right-2 cursor-pointer rounded-lg p-1 text-on-surface-variant hover:bg-error-red/10 hover:text-error-red"
                        :title="t('nav.delete_chat')"
                    >
                        <Trash2 :size="12" />
                    </button>
                </div>
            </div>
            <p v-else class="px-3 py-2 text-xs text-on-surface-variant">
                {{ t('nav.no_chats') }}
            </p>
        </div>

        <!-- Main Workspace -->
        <main
            id="main-content"
            class="flex h-screen flex-1 flex-col overflow-hidden"
        >
            <div
                class="relative flex flex-1 flex-col overflow-hidden p-4 md:p-8"
            >
                <!-- Ambient Decorator -->
                <div
                    class="pointer-events-none absolute top-1/2 left-1/2 h-[70vw] w-[70vw] -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary/5 blur-[100px]"
                ></div>

                <div
                    class="z-10 flex flex-1 flex-col overflow-hidden max-w-4xl w-full mx-auto"
                >
                    <FlashAlerts />
                    <ConfirmDialog />

                    <div class="flex flex-1 flex-col overflow-hidden">
                        <slot />
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
.list-enter-active,
.list-leave-active {
    transition: all 0.2s ease-in-out;
}
.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: scale(0.95) translateX(-10px);
}
</style>
