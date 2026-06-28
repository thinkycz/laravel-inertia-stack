<script setup lang="ts">
import { ref, watch, nextTick, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import type {
    AgentRunSnapshot,
    ChatConversation,
    ChatMessage,
    SharedProps,
} from '@/types';
import { useI18n } from 'vue-i18n';
import { Send, Sparkles, Bot, User } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useActiveConversation } from '@/composables/useActiveConversation';
import { useAgentRunBridge } from '@/composables/useAgentRunBridge';
import RunStatusBadge from '@/components/agent/RunStatusBadge.vue';

interface LocalMessage extends ChatMessage {
    id: string;
}

const props = defineProps<{
    conversation?: ChatConversation;
    activeRun?: AgentRunSnapshot | null;
}>();

const { t } = useI18n();
useBoundLocale();
const { pendingConversationId } = useActiveConversation();
const page = usePage<SharedProps>();

const inputMessage = ref('');
const messagesContainer = ref<HTMLElement | null>(null);
const chatTextarea = ref<HTMLTextAreaElement | null>(null);

const localMessages = ref<LocalMessage[]>([]);
const localProcessing = ref(false);
const currentTool = ref<string | null>(null);
// Tracks the index of the assistant message currently being streamed,
// so the loading indicator only shows for that specific bubble.
const streamingMsgIndex = ref(-1);

let messageIdCounter = 0;
function generateMessageId(): string {
    return `msg-${++messageIdCounter}`;
}

let isScrollScheduled = false;

function scrollToBottom(): void {
    if (isScrollScheduled) {
        return;
    }

    isScrollScheduled = true;
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop =
                messagesContainer.value.scrollHeight;
        }
        isScrollScheduled = false;
    });
}

// Suggestion chips — icons are module-level constants so no need for computed.
const suggestions = [
    { icon: Sparkles, textKey: 'dashboard.suggestions.inertia' as const },
    { icon: Sparkles, textKey: 'dashboard.suggestions.route' as const },
    { icon: Sparkles, textKey: 'dashboard.suggestions.schema' as const },
] as const;

function resizeTextarea(): void {
    if (chatTextarea.value) {
        chatTextarea.value.style.height = 'auto';
        chatTextarea.value.style.height = `${chatTextarea.value.scrollHeight}px`;
    }
}

watch(inputMessage, () => {
    nextTick(resizeTextarea);
});

const bridge = useAgentRunBridge({
    initialRun: props.activeRun ?? null,
    onRunStarted: () => {
        // No-op: submitMessage handles the optimistic sidebar insert after
        // bridge.start() resolves, and on mount there is nothing to insert.
    },
    onTextDelta: (delta) => {
        if (
            streamingMsgIndex.value >= 0 &&
            streamingMsgIndex.value < localMessages.value.length
        ) {
            const current = localMessages.value[streamingMsgIndex.value];
            current.content = (current.content ?? '') + delta;
            scrollToBottom();
        }
    },
    onToolActivity: (tool) => {
        currentTool.value = tool;
    },
    onTerminal: (status, error) => {
        if (
            streamingMsgIndex.value >= 0 &&
            streamingMsgIndex.value < localMessages.value.length
        ) {
            const bubble = localMessages.value[streamingMsgIndex.value];

            if (status === 'failed' && error !== null) {
                bubble.content = error;
            } else if (status === 'cancelled') {
                bubble.content = (bubble.content ?? '') + '\n\n[cancelled]';
            }
        }

        currentTool.value = null;
        localProcessing.value = false;

        if (status === 'completed') {
            void router.reload({ only: ['conversation', 'active_run'] });
        }
    },
    onError: (message) => {
        if (
            streamingMsgIndex.value >= 0 &&
            streamingMsgIndex.value < localMessages.value.length
        ) {
            localMessages.value[streamingMsgIndex.value].content = message;
        }

        currentTool.value = null;
        localProcessing.value = false;
    },
});

function selectSuggestion(text: string): void {
    inputMessage.value = text;
}

function applySnapshot(
    conv: ChatConversation | undefined,
    run: AgentRunSnapshot | null,
): void {
    if (conv) {
        localMessages.value = conv.messages.map((m) => ({
            id: generateMessageId(),
            role: m.role,
            content: m.content,
        }));
    } else {
        localMessages.value = [];
    }

    if (run !== null && (run.status === 'queued' || run.status === 'running')) {
        const newIndex =
            localMessages.value.push({
                id: generateMessageId(),
                role: 'assistant',
                content: run.assistant_content,
            }) - 1;
        streamingMsgIndex.value = newIndex;
        localProcessing.value = true;
    } else {
        streamingMsgIndex.value = -1;
        localProcessing.value = false;
    }

    scrollToBottom();
}

watch(
    [() => props.conversation, () => props.activeRun],
    ([newConv, newActiveRun]) => {
        applySnapshot(newConv, newActiveRun ?? null);
    },
    { immediate: true },
);

async function submitMessage(): Promise<void> {
    if (!inputMessage.value.trim() || localProcessing.value) {
        return;
    }

    const promptText = inputMessage.value;
    inputMessage.value = '';

    // Remove any leftover empty assistant bubbles from a previous failed/incomplete run.
    while (
        localMessages.value.length > 0 &&
        localMessages.value[localMessages.value.length - 1].role ===
            'assistant' &&
        (localMessages.value[localMessages.value.length - 1].content ?? '') ===
            ''
    ) {
        localMessages.value.pop();
    }

    // Instantly append user message and empty assistant message block.
    localMessages.value.push({
        id: generateMessageId(),
        role: 'user',
        content: promptText,
    });
    streamingMsgIndex.value =
        localMessages.value.push({
            id: generateMessageId(),
            role: 'assistant',
            content: '',
        }) - 1;
    localProcessing.value = true;
    scrollToBottom();

    try {
        const started = await bridge.start({
            prompt: promptText,
            conversationId: props.conversation?.id ?? null,
        });

        // For brand-new conversations, optimistically surface the id in the
        // sidebar and ask AppLayout to highlight it without a full navigation.
        if (!props.conversation && page.props.conversations) {
            const title =
                promptText.length > 35
                    ? `${promptText.slice(0, 35)}...`
                    : promptText;
            page.props.conversations.unshift({
                id: started.conversation_id,
                title,
                updated_at: new Date().toISOString(),
            });
            pendingConversationId.value = started.conversation_id;
        }
    } catch (e: unknown) {
        console.error('Failed to start agent run:', e);
        if (
            streamingMsgIndex.value >= 0 &&
            streamingMsgIndex.value < localMessages.value.length
        ) {
            localMessages.value[streamingMsgIndex.value].content =
                e instanceof Error && e.message
                    ? e.message
                    : t('agent.error_generic');
        }
        localProcessing.value = false;
        streamingMsgIndex.value = -1;
    }
}

async function onCancelRun(): Promise<void> {
    await bridge.cancel();
}

// Bypass Vue's reactivity for the ref returned by the bridge composable.
const activeRunSnapshot = computed<AgentRunSnapshot | null>(
    () => bridge.activeRun.value,
);

const isRunActive = computed<boolean>(
    () =>
        activeRunSnapshot.value !== null &&
        (activeRunSnapshot.value.status === 'queued' ||
            activeRunSnapshot.value.status === 'running'),
);
</script>

<template>
    <AppLayout
        :title="conversation ? conversation.title : t('dashboard.title')"
    >
        <div class="flex flex-1 flex-col overflow-hidden">
            <!-- Active Conversation State -->
            <div
                v-if="localMessages.length > 0"
                ref="messagesContainer"
                class="flex-1 overflow-y-auto space-y-4 pr-2 mb-4 scrollbar-thin"
            >
                <div
                    v-for="(msg, idx) in localMessages"
                    :key="msg.id"
                    class="flex gap-4 p-4 rounded-2xl border transition-all"
                    :class="[
                        msg.role === 'user'
                            ? 'bg-primary/5 border-primary/10 ml-12 justify-end text-right'
                            : 'bg-surface-container-lowest border-outline-glass mr-12',
                    ]"
                >
                    <!-- Role Avatar -->
                    <div
                        v-if="msg.role !== 'user'"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-primary text-white"
                    >
                        <Bot :size="16" />
                    </div>

                    <div class="flex-1 space-y-1.5 overflow-hidden text-left">
                        <p class="text-xs font-bold text-on-surface">
                            {{
                                msg.role === 'user'
                                    ? t('fields.user')
                                    : t('fields.assistant')
                            }}
                        </p>
                        <!-- Message Content -->
                        <div
                            class="text-xs text-on-surface-variant whitespace-pre-wrap leading-relaxed"
                        >
                            <span v-if="msg.content">{{ msg.content }}</span>
                            <div
                                v-else-if="
                                    localProcessing && idx === streamingMsgIndex
                                "
                                class="flex gap-1 items-center py-1"
                            >
                                <span
                                    class="h-1.5 w-1.5 animate-bounce bg-primary rounded-full [animation-delay:-0.3s]"
                                ></span>
                                <span
                                    class="h-1.5 w-1.5 animate-bounce bg-primary rounded-full [animation-delay:-0.15s]"
                                ></span>
                                <span
                                    class="h-1.5 w-1.5 animate-bounce bg-primary rounded-full"
                                ></span>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="msg.role === 'user'"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-surface-container border border-outline-glass text-primary"
                    >
                        <User :size="16" />
                    </div>
                </div>
            </div>

            <!-- Empty / New Chat State -->
            <div
                v-else
                class="flex-1 flex flex-col justify-center items-center max-w-2xl mx-auto w-full text-center space-y-8"
            >
                <div class="space-y-4">
                    <div
                        class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary mb-2"
                    >
                        <Sparkles :size="24" />
                    </div>
                    <h2
                        class="font-heading text-2xl font-black tracking-tight text-on-surface md:text-3xl"
                    >
                        {{ t('dashboard.how_can_i_help') }}
                    </h2>
                    <p
                        class="text-xs text-on-surface-variant font-medium max-w-md"
                    >
                        {{ t('dashboard.chat_description') }}
                    </p>
                </div>

                <!-- Prompt Suggestions Grid -->
                <div class="grid gap-3 w-full sm:grid-cols-3">
                    <button
                        v-for="(sug, sugIdx) in suggestions"
                        :key="sugIdx"
                        @click="selectSuggestion(t(sug.textKey))"
                        class="flex flex-col items-start gap-2 p-4 text-left rounded-xl border border-outline-glass bg-surface-container-lowest hover:border-primary/30 transition-all cursor-pointer shadow-sm"
                    >
                        <component
                            :is="sug.icon"
                            class="text-primary"
                            :size="14"
                        />
                        <span
                            class="text-[11px] font-semibold text-on-surface leading-snug"
                            >{{ t(sug.textKey) }}</span
                        >
                    </button>
                </div>
            </div>

            <!-- Run Status Badge (above input) -->
            <RunStatusBadge
                v-if="
                    isRunActive ||
                    activeRunSnapshot?.status === 'failed' ||
                    activeRunSnapshot?.status === 'cancelled'
                "
                :run="activeRunSnapshot"
                :current-tool="currentTool"
                @cancel="onCancelRun"
            />

            <!-- Chat Input form -->
            <form
                @submit.prevent="submitMessage"
                class="relative max-w-3xl w-full mx-auto mt-auto"
            >
                <div
                    class="flex items-center rounded-2xl border border-outline-glass bg-surface-container-lowest p-2 shadow-lg focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 transition-all"
                >
                    <textarea
                        ref="chatTextarea"
                        v-model="inputMessage"
                        rows="1"
                        :placeholder="t('dashboard.type_message')"
                        :disabled="isRunActive"
                        class="flex-1 resize-none bg-transparent py-2 px-3 text-xs outline-none text-on-surface placeholder:text-on-surface-variant/50 max-h-32 overflow-y-auto scrollbar-none disabled:opacity-60"
                        @keydown.enter.exact="submitMessage"
                        @keydown.enter.shift.exact.prevent
                        @keydown.enter.meta.exact.prevent="submitMessage"
                        @keydown.enter.ctrl.exact.prevent="submitMessage"
                    ></textarea>
                    <button
                        type="submit"
                        :disabled="!inputMessage.trim() || localProcessing"
                        :aria-label="t('dashboard.send')"
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary text-white transition-all disabled:opacity-50 disabled:cursor-not-allowed hover:bg-primary-container cursor-pointer shrink-0"
                    >
                        <Send :size="14" />
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
