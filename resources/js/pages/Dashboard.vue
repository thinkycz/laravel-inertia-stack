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
import { useAgentRunBridge } from '@/composables/useAgentRunBridge';
import RunStatusBadge from '@/components/agent/RunStatusBadge.vue';
import ClarificationCard from '@/components/agent/ClarificationCard.vue';

const props = defineProps<{
    conversation?: ChatConversation;
    active_run?: AgentRunSnapshot | null;
}>();
const { t } = useI18n();
useBoundLocale();
const page = usePage<SharedProps>();
const inputMessage = ref('');
const messagesContainer = ref<HTMLElement | null>(null);
const chatTextarea = ref<HTMLTextAreaElement | null>(null);
const localMessages = ref<ChatMessage[]>([]);
const canonicalId = ref<string | null>(props.conversation?.id ?? null);
const submitting = ref(false);
const pendingRefreshes = ref(0);
const currentTool = ref<string | null>(null);
const errorKey = ref<string | null>(null);
let viewGeneration = 0;
let counter = 0;
function refreshConversation(): void {
    pendingRefreshes.value++;
    router.reload({
        only: ['conversation', 'active_run', 'conversations'],
        onFinish: () => {
            pendingRefreshes.value--;
        },
    });
}
function scrollToBottom(): void {
    void nextTick(() => {
        if (messagesContainer.value)
            messagesContainer.value.scrollTop =
                messagesContainer.value.scrollHeight;
    });
}
const suggestions = [
    { icon: Sparkles, textKey: 'dashboard.suggestions.inertia' },
    { icon: Sparkles, textKey: 'dashboard.suggestions.route' },
    { icon: Sparkles, textKey: 'dashboard.suggestions.schema' },
];
watch(inputMessage, () => {
    void nextTick(() => {
        if (chatTextarea.value) {
            chatTextarea.value.style.height = 'auto';
            chatTextarea.value.style.height = `${chatTextarea.value.scrollHeight}px`;
        }
    });
});
function reconcile(run: AgentRunSnapshot): ChatMessage {
    let message = localMessages.value.find(
        (m) => m.id === run.assistant_message_id,
    );
    if (!message) {
        message = {
            id: run.assistant_message_id,
            role: 'assistant',
            content: run.assistant_content,
        };
        localMessages.value.push(message);
        message = localMessages.value[localMessages.value.length - 1];
    }
    message.content = run.assistant_content;
    message.status = run.status;
    return message;
}
const bridge = useAgentRunBridge({
    initialRun: null,
    onRunStarted: (run) => {
        canonicalId.value = run.conversation_id;
        reconcile(run);
    },
    onTextDelta: () => {
        if (bridge.activeRun.value) reconcile(bridge.activeRun.value);
        scrollToBottom();
    },
    onToolActivity: (tool) => {
        currentTool.value = tool;
    },
    onClarification: (clarification) => {
        if (bridge.activeRun.value)
            reconcile(bridge.activeRun.value).clarification = clarification;
    },
    onTerminal: () => {
        if (bridge.activeRun.value) reconcile(bridge.activeRun.value);
        currentTool.value = null;
        submitting.value = false;
        if (
            canonicalId.value &&
            page.url.startsWith(`/conversations/${canonicalId.value}`)
        )
            refreshConversation();
    },
    onError: (key) => {
        errorKey.value = key;
    },
});
const activeRunSnapshot = bridge.activeRun;
const isRunActive = computed(
    () =>
        activeRunSnapshot.value?.status === 'queued' ||
        activeRunSnapshot.value?.status === 'running',
);
const localProcessing = computed(
    () => submitting.value || isRunActive.value || pendingRefreshes.value > 0,
);
const streamingMsgIndex = computed(() =>
    localMessages.value.findIndex(
        (m) => m.id === activeRunSnapshot.value?.assistant_message_id,
    ),
);
watch(
    [() => props.conversation, () => props.active_run],
    ([conversation, run]) => {
        ++viewGeneration;
        canonicalId.value = conversation?.id ?? null;
        localMessages.value =
            conversation?.messages.map((message) => ({ ...message })) ?? [];
        submitting.value = false;
        errorKey.value = null;
        currentTool.value = null;
        if (run) reconcile(run);
        bridge.attach(run ?? null);
        scrollToBottom();
    },
    { immediate: true },
);
function messageStatusLabel(message: ChatMessage): string {
    if (message.status === 'cancelled') return t('agent.cancelled');
    const run = activeRunSnapshot.value;
    return t(
        run?.assistant_message_id === message.id && run.error
            ? run.error
            : 'ai.errors.provider_failed',
    );
}
function selectSuggestion(text: string): void {
    inputMessage.value = text;
}
async function submitMessage(clarificationMessageId?: string): Promise<void> {
    if (!inputMessage.value.trim() || localProcessing.value) return;
    const latest = localMessages.value.at(-1);
    if (
        !clarificationMessageId &&
        latest?.clarification &&
        !latest.clarification_answer
    )
        clarificationMessageId = latest.id;
    const prompt = inputMessage.value.trim();
    inputMessage.value = '';
    submitting.value = true;
    errorKey.value = null;
    const version = viewGeneration;
    const temporaryId = `optimistic-${++counter}`;
    localMessages.value.push({
        id: temporaryId,
        role: 'user',
        content: prompt,
    });
    scrollToBottom();
    try {
        const run = await bridge.start({
            prompt,
            conversationId: canonicalId.value,
            clarificationMessageId,
        });
        if (version !== viewGeneration) return;
        const selected = localMessages.value.find(
            (message) => message.id === clarificationMessageId,
        );
        if (selected) selected.clarification_answer = prompt;
        const title =
            props.conversation?.title ??
            (prompt.length > 35 ? `${prompt.slice(0, 35)}...` : prompt);
        if (
            !page.props.conversations.some(
                (conversation) => conversation.id === run.conversation_id,
            )
        )
            page.props.conversations.unshift({
                id: run.conversation_id,
                title,
                updated_at: new Date().toISOString(),
            });
        pendingRefreshes.value++;
        router.replace({
            url: `/conversations/${run.conversation_id}`,
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                if (version === viewGeneration) refreshConversation();
                pendingRefreshes.value--;
            },
        });
    } catch (error) {
        if (
            version !== viewGeneration ||
            (error instanceof DOMException && error.name === 'AbortError')
        )
            return;
        localMessages.value = localMessages.value.filter(
            (message) => message.id !== temporaryId,
        );
        inputMessage.value = prompt;
        errorKey.value = 'agent.error_generic';
    } finally {
        if (version === viewGeneration) submitting.value = false;
    }
}
function selectClarification(message: ChatMessage, option: string): void {
    if (message.clarification_answer || localProcessing.value) return;
    inputMessage.value = option;
    void submitMessage(message.id);
}
function onComposerKey(event: KeyboardEvent): void {
    if (
        event.key !== 'Enter' ||
        event.shiftKey ||
        event.isComposing ||
        event.keyCode === 229
    )
        return;
    event.preventDefault();
    void submitMessage();
}
async function onCancelRun(): Promise<void> {
    errorKey.value = null;
    await bridge.cancel();
}
function retryConnection(): void {
    errorKey.value = null;
    bridge.retry();
}
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
                        <p
                            v-if="
                                msg.role === 'assistant' &&
                                (msg.status === 'failed' ||
                                    msg.status === 'cancelled')
                            "
                            class="text-xs text-on-surface-variant"
                        >
                            {{ messageStatusLabel(msg) }}
                        </p>
                        <ClarificationCard
                            v-if="msg.clarification"
                            :clarification="msg.clarification"
                            :disabled="localProcessing"
                            :selected-option="msg.clarification_answer ?? null"
                            :show-question="true"
                            @select="selectClarification(msg, $event)"
                        />
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
                v-if="isRunActive"
                :run="activeRunSnapshot"
                :current-tool="currentTool"
                :cancelling="bridge.cancelling.value"
                @cancel="onCancelRun"
            />

            <div
                v-if="
                    errorKey || bridge.connectionStatus.value === 'reconnecting'
                "
                role="status"
                class="mx-auto mb-2 max-w-3xl text-xs"
            >
                {{ t(errorKey ?? 'agent.reconnecting') }}
                <button
                    v-if="bridge.connectionStatus.value === 'disconnected'"
                    type="button"
                    class="ml-2 underline"
                    @click="retryConnection"
                >
                    {{ t('agent.retry_connection') }}
                </button>
            </div>
            <!-- Chat Input form -->
            <form
                @submit.prevent="submitMessage()"
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
                        :disabled="localProcessing"
                        class="flex-1 resize-none bg-transparent py-2 px-3 text-xs outline-none text-on-surface placeholder:text-on-surface-variant/50 max-h-32 overflow-y-auto scrollbar-none disabled:opacity-60"
                        @keydown="onComposerKey"
                        :aria-label="t('dashboard.type_message')"
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
