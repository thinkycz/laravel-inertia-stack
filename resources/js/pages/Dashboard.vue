<script setup lang="ts">
import { ref, watch, nextTick, onBeforeUnmount } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';
import { useI18n } from 'vue-i18n';
import { Send, Sparkles, Bot, User } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import { useActiveConversation } from '@/composables/useActiveConversation';

interface Message {
    role: 'user' | 'assistant';
    content: string;
}

interface LocalMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
}

interface Conversation {
    id: string;
    title: string;
    messages: Message[];
}

const props = defineProps<{
    conversation?: Conversation;
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
// Tracks the index of the assistant message currently being streamed,
// so the loading indicator only shows for that specific bubble.
const streamingMsgIndex = ref(-1);

let messageIdCounter = 0;
function generateMessageId(): string {
    return `msg-${++messageIdCounter}`;
}

let activeAbortController: AbortController | null = null;

onBeforeUnmount(() => {
    if (activeAbortController) {
        activeAbortController.abort();
    }
});

let isScrollScheduled = false;

function scrollToBottom() {
    if (isScrollScheduled) return;
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

function removeOptimisticConversation(id: string): void {
    const conversations = page.props.conversations;
    const index = conversations.findIndex(
        (conversation) => conversation.id === id,
    );

    if (index >= 0) {
        conversations.splice(index, 1);
    }
}

function resizeTextarea() {
    if (chatTextarea.value) {
        chatTextarea.value.style.height = 'auto';
        chatTextarea.value.style.height = `${chatTextarea.value.scrollHeight}px`;
    }
}

watch(inputMessage, () => {
    nextTick(resizeTextarea);
});

// Sync local messages with props when prop changes (only if not currently streaming)
watch(
    () => props.conversation,
    (newConv) => {
        if (localProcessing.value) return;
        if (newConv) {
            localMessages.value = newConv.messages.map((m) => ({
                id: generateMessageId(),
                role: m.role,
                content: m.content,
            }));
        } else {
            localMessages.value = [];
        }
        scrollToBottom();
    },
    { immediate: true },
);

// Sync local messages with props when streaming completes
watch(localProcessing, (processing) => {
    if (!processing && props.conversation) {
        localMessages.value = props.conversation.messages.map((m) => ({
            id: generateMessageId(),
            role: m.role,
            content: m.content,
        }));
        scrollToBottom();
    }
});

function selectSuggestion(text: string) {
    inputMessage.value = text;
}

async function submitMessage() {
    if (!inputMessage.value.trim() || localProcessing.value) return;

    const promptText = inputMessage.value;
    inputMessage.value = '';

    localProcessing.value = true;
    activeAbortController = new AbortController();

    // Remove any leftover empty assistant bubbles from a previous failed/incomplete stream.
    while (
        localMessages.value.length > 0 &&
        localMessages.value[localMessages.value.length - 1].role ===
            'assistant' &&
        localMessages.value[localMessages.value.length - 1].content === ''
    ) {
        localMessages.value.pop();
    }

    // Instantly append user message and empty assistant message block
    localMessages.value.push({
        id: generateMessageId(),
        role: 'user',
        content: promptText,
    });
    const assistantMsgIndex =
        localMessages.value.push({
            id: generateMessageId(),
            role: 'assistant',
            content: '',
        }) - 1;
    streamingMsgIndex.value = assistantMsgIndex;
    scrollToBottom();

    const url = props.conversation
        ? `/conversations/${props.conversation.id}/messages`
        : '/conversations';
    let newConversationId: string | null = null;

    try {
        const xsrfToken = document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1];

        const response = await fetch(url, {
            method: 'POST',
            signal: activeAbortController.signal,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'text/event-stream',
                'X-XSRF-TOKEN': xsrfToken ? decodeURIComponent(xsrfToken) : '',
            },
            body: JSON.stringify({ message: promptText }),
        });

        if (!response.ok || !response.body) {
            throw new Error('Failed to generate response');
        }

        const conversationIdHeader = response.headers.get('X-Conversation-ID');
        if (conversationIdHeader && !props.conversation) {
            newConversationId = conversationIdHeader;
            if (page.props.conversations) {
                const title =
                    promptText.length > 35
                        ? promptText.slice(0, 35) + '...'
                        : promptText;
                page.props.conversations.unshift({
                    id: conversationIdHeader,
                    title: title,
                    updated_at: new Date().toISOString(),
                });
            }
            // Signal the sidebar to highlight this conversation immediately,
            // without navigating (which would wipe local streaming state).
            pendingConversationId.value = conversationIdHeader;
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';

            for (const line of lines) {
                const cleaned = line.trim();
                if (!cleaned || !cleaned.startsWith('data: ')) continue;
                const dataStr = cleaned.slice(6);
                if (dataStr === '[DONE]') continue;

                let data: unknown;
                try {
                    data = JSON.parse(dataStr);
                } catch {
                    // Ignore parsing errors for incomplete data chunks
                    continue;
                }

                if (
                    typeof data !== 'object' ||
                    data === null ||
                    !('type' in data)
                ) {
                    continue;
                }

                if (data.type === 'text_delta' && 'delta' in data) {
                    localMessages.value[assistantMsgIndex].content += String(
                        data.delta,
                    );
                    scrollToBottom();
                } else if (data.type === 'error') {
                    throw new Error(
                        'message' in data && typeof data.message === 'string'
                            ? data.message
                            : t('errors.failed_response'),
                    );
                } else if (
                    data.type === 'done' &&
                    'conversation_id' in data &&
                    typeof data.conversation_id === 'string'
                ) {
                    const newId = data.conversation_id;
                    // Clear the pending state and navigate properly now that
                    // streaming is complete.
                    pendingConversationId.value = null;
                    if (
                        !props.conversation ||
                        props.conversation.id !== newId
                    ) {
                        router.visit(`/conversations/${newId}`, {
                            preserveScroll: true,
                            replace: true,
                        });
                    } else {
                        router.reload();
                    }
                }
            }
        }
    } catch (e: unknown) {
        if (e instanceof DOMException && e.name === 'AbortError') {
            return;
        }
        console.error('Error during chat stream:', e);
        if (newConversationId) {
            removeOptimisticConversation(newConversationId);
        }
        localMessages.value[assistantMsgIndex].content =
            e instanceof Error && e.message
                ? e.message
                : t('errors.failed_response');
    } finally {
        localProcessing.value = false;
        streamingMsgIndex.value = -1;
        pendingConversationId.value = null;
        activeAbortController = null;
        scrollToBottom();
    }
}

// Scroll to bottom when conversation changes or new messages arrive
watch(
    () => props.conversation?.messages,
    () => {
        scrollToBottom();
    },
    { deep: true, immediate: true },
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
                                    ? t('fields.user') || 'You'
                                    : t('fields.assistant') || 'Assistant'
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
                        {{
                            t('dashboard.how_can_i_help') ||
                            'How can I help you today?'
                        }}
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
                        :placeholder="
                            t('dashboard.type_message') || 'Type a message...'
                        "
                        class="flex-1 resize-none bg-transparent py-2 px-3 text-xs outline-none text-on-surface placeholder:text-on-surface-variant/50 max-h-32 overflow-y-auto scrollbar-none"
                        @keydown.enter.exact.prevent="submitMessage"
                    ></textarea>
                    <button
                        type="submit"
                        :disabled="!inputMessage.trim() || localProcessing"
                        class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary text-white transition-all disabled:opacity-50 disabled:cursor-not-allowed hover:bg-primary-container cursor-pointer shrink-0"
                    >
                        <Send :size="14" />
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
