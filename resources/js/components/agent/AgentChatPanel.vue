<script setup lang="ts">
import { computed, nextTick, onScopeDispose, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useChat } from '@ai-sdk/vue';
import {
    DefaultChatTransport,
    getToolName,
    isToolUIPart,
    type UIMessage,
} from 'ai';
import { Bot, Send, Sparkles, Square, User } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import NotificationApprovalCard from '@/components/agent/NotificationApprovalCard.vue';
import { useBoundLocale } from '@/composables/useBoundLocale';
import {
    approveNotification,
    hasPendingApprovals,
    messageText,
    rejectTool,
    safeAgentErrorKey,
    canRespondToApproval,
    shouldSendComposerKey,
} from '@/lib/agentChat';
import type {
    AgentToolDecision,
    ChatConversation,
    NotificationApprovalArguments,
    SharedProps,
} from '@/types';

const props = defineProps<{
    conversation?: ChatConversation;
}>();

const { t } = useI18n();
useBoundLocale();

const page = usePage<SharedProps>();
const conversationId = ref<string | null>(props.conversation?.id ?? null);
const inputMessage = ref('');
const messagesContainer = ref<HTMLElement | null>(null);
const chatTextarea = ref<HTMLTextAreaElement | null>(null);
const decisions = ref<Record<string, AgentToolDecision>>({});
const latestSubmittedMessage = ref('');
const settling = ref(false);
const approvalSubmitting = ref(false);
const stopped = ref(false);
let disposed = false;

function csrfHeaders(): Record<string, string> {
    const cookie = document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='));
    return {
        'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.slice(11)) : '',
    };
}

function scrollToBottom(): void {
    void nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop =
                messagesContainer.value.scrollHeight;
        }
    });
}

function resizeTextarea(): void {
    if (!chatTextarea.value) {
        return;
    }

    chatTextarea.value.style.height = 'auto';
    chatTextarea.value.style.height = `${chatTextarea.value.scrollHeight}px`;
}

async function registerConversation(response: Response): Promise<void> {
    const resolvedId = response.headers.get('x-conversation-id');
    if (disposed || !resolvedId || conversationId.value === resolvedId) return;
    conversationId.value = resolvedId;
    if (!page.props.conversations.some((item) => item.id === resolvedId)) {
        page.props.conversations.unshift({
            id: resolvedId,
            title: latestSubmittedMessage.value.slice(0, 100),
            updated_at: new Date().toISOString(),
        });
    }
    await new Promise<void>((resolve) =>
        router.replace({
            url: `/conversations/${resolvedId}`,
            preserveState: true,
            preserveScroll: true,
            onFinish: () => resolve(),
        }),
    );
}

const transport = new DefaultChatTransport<UIMessage>({
    api: '/agent/chat',
    credentials: 'same-origin',
    headers: {
        Accept: 'text/event-stream',
        'Content-Type': 'application/json',
    },
    fetch: async (input, init) => {
        const response = await fetch(input, {
            ...init,
            headers: {
                ...Object.fromEntries(new Headers(init?.headers)),
                ...csrfHeaders(),
            },
        });
        if (disposed || init?.signal?.aborted)
            throw new DOMException('Aborted', 'AbortError');
        if (response.ok) await registerConversation(response);
        if (disposed || init?.signal?.aborted)
            throw new DOMException('Aborted', 'AbortError');
        return response;
    },
    prepareSendMessagesRequest: ({ messages: requestMessages, body }) => {
        if (body && 'decisions' in body) {
            return {
                body: {
                    conversation_id: conversationId.value,
                    decisions: body.decisions,
                },
            };
        }

        const userMessage = [...requestMessages]
            .reverse()
            .find((message) => message.role === 'user');

        return {
            body: {
                message: userMessage ? messageText(userMessage) : '',
                conversation_id: conversationId.value,
            },
        };
    },
});

const {
    messages,
    status,
    error,
    sendMessage,
    stop,
    addToolApprovalResponse,
    clearError,
} = useChat<UIMessage>({
    id: props.conversation?.id ?? 'new-agent-chat',
    messages: props.conversation?.messages ?? [],
    transport,
    onFinish: ({ isAbort, isError }) => {
        if (disposed) return;
        scrollToBottom();
        if (!isAbort && !isError) refreshConversation();
    },
});

function refreshConversation(): void {
    if (disposed || settling.value) return;
    settling.value = true;
    router.reload({
        only: ['conversation', 'conversations'],
        onSuccess: () => {
            if (!disposed) {
                stopped.value = false;
                messages.value = props.conversation?.messages ?? [];
                decisions.value = {};
                clearError();
            }
        },
        onFinish: () => {
            if (!disposed) {
                settling.value = false;
                approvalSubmitting.value = false;
            }
        },
    });
}
onScopeDispose(() => {
    disposed = true;
    void stop();
});

const isStreaming = computed(
    () => status.value === 'submitted' || status.value === 'streaming',
);
const hasPendingApproval = computed(
    () =>
        hasPendingApprovals(messages.value) ||
        (!!error.value && Object.keys(decisions.value).length > 0),
);
const busy = computed(
    () => isStreaming.value || settling.value || approvalSubmitting.value,
);
const safeError = computed(() =>
    error.value ? t(safeAgentErrorKey(error.value.message)) : '',
);

const suggestions = [
    'dashboard.suggestions.inertia',
    'dashboard.suggestions.route',
    'dashboard.suggestions.schema',
] as const;

watch(inputMessage, () => void nextTick(resizeTextarea));
watch(messages, scrollToBottom, { deep: true });
watch(
    () => props.conversation,
    (conversation) => {
        if (disposed) return;
        conversationId.value = conversation?.id ?? null;
        messages.value = conversation?.messages ?? [];
        decisions.value = {};
        approvalSubmitting.value = false;
    },
);

function selectSuggestion(key: (typeof suggestions)[number]): void {
    inputMessage.value = t(key);
}

async function submitMessage(): Promise<void> {
    const text = inputMessage.value.trim();

    if (!text || busy.value || hasPendingApproval.value || stopped.value) {
        return;
    }

    latestSubmittedMessage.value = text;
    inputMessage.value = '';
    clearError();
    await sendMessage({ text });
    if (!disposed && error.value) inputMessage.value = text;
}

async function respondToApproval(
    approvalId: string,
    decision: AgentToolDecision,
): Promise<void> {
    if (
        busy.value ||
        stopped.value ||
        !canRespondToApproval(messages.value, decisions.value, approvalId)
    )
        return;
    decisions.value[approvalId] = decision;
    await addToolApprovalResponse({
        id: approvalId,
        approved: decision.action === 'approve',
        reason: decision.result,
    });
    if (
        disposed ||
        hasPendingApprovals(messages.value) ||
        approvalSubmitting.value
    )
        return;
    approvalSubmitting.value = true;
    clearError();
    try {
        await sendMessage(undefined, {
            body: { decisions: { ...decisions.value } },
        });
    } finally {
        if (!disposed) approvalSubmitting.value = false;
    }
}
async function approveTool(
    approvalId: string,
    argumentsValue: NotificationApprovalArguments,
): Promise<void> {
    if (!argumentsValue.subject.trim() || !argumentsValue.message.trim())
        return;
    await respondToApproval(approvalId, approveNotification(argumentsValue));
}
async function rejectApproval(
    approvalId: string,
    result: string,
): Promise<void> {
    await respondToApproval(approvalId, rejectTool(result));
}
function onComposerKey(event: KeyboardEvent): void {
    if (!shouldSendComposerKey(event)) return;
    event.preventDefault();
    void submitMessage();
}
async function stopResponse(): Promise<void> {
    stopped.value = true;
    await stop();
}

function notificationArguments(
    part: UIMessage['parts'][number],
): NotificationApprovalArguments {
    if (!isToolUIPart(part) || typeof part.input !== 'object' || !part.input) {
        return { subject: '', message: '' };
    }

    const input = part.input as Record<string, unknown>;

    return {
        subject: typeof input.subject === 'string' ? input.subject : '',
        message: typeof input.message === 'string' ? input.message : '',
    };
}

function outputText(output: unknown): string {
    return typeof output === 'string' ? output : JSON.stringify(output);
}
</script>

<template>
    <div class="flex flex-1 flex-col overflow-hidden">
        <div
            v-if="messages.length > 0"
            ref="messagesContainer"
            role="log"
            :aria-label="t('dashboard.title')"
            :aria-busy="isStreaming"
            class="mb-4 flex-1 space-y-4 overflow-y-auto pr-2 scrollbar-thin"
        >
            <div
                v-for="message in messages"
                :key="message.id"
                class="flex gap-4 rounded-2xl border p-4 transition-all"
                :class="
                    message.role === 'user'
                        ? 'ml-12 justify-end border-primary/10 bg-primary/5 text-right'
                        : 'mr-12 border-outline-glass bg-surface-container-lowest'
                "
            >
                <div
                    v-if="message.role !== 'user'"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-primary text-white"
                >
                    <Bot :size="16" />
                </div>

                <div class="flex-1 space-y-2 overflow-hidden text-left">
                    <p class="text-xs font-bold text-on-surface">
                        {{
                            message.role === 'user'
                                ? t('fields.user')
                                : t('fields.assistant')
                        }}
                    </p>

                    <template
                        v-for="(part, partIndex) in message.parts"
                        :key="`${message.id}-${partIndex}`"
                    >
                        <p
                            v-if="part.type === 'text'"
                            class="whitespace-pre-wrap text-xs leading-relaxed text-on-surface-variant"
                        >
                            {{ part.text }}
                        </p>

                        <NotificationApprovalCard
                            v-else-if="
                                isToolUIPart(part) &&
                                getToolName(part) ===
                                    'SendTestNotificationTool' &&
                                part.state === 'approval-requested'
                            "
                            :arguments-value="notificationArguments(part)"
                            :disabled="
                                busy || stopped || !!decisions[part.approval.id]
                            "
                            @approve="approveTool(part.approval.id, $event)"
                            @reject="rejectApproval(part.approval.id, $event)"
                        />

                        <div
                            v-else-if="isToolUIPart(part)"
                            class="rounded-lg border border-outline-glass bg-surface-container-low px-3 py-2 text-[11px] text-on-surface-variant"
                        >
                            <span class="font-bold text-on-surface">
                                {{ t('agent.approval.tool_name') }}
                            </span>
                            <span v-if="part.state === 'approval-responded'">
                                — {{ t('agent.approval.processing') }}
                            </span>
                            <span v-else-if="part.state === 'output-denied'">
                                — {{ t('agent.approval.rejected') }}
                            </span>
                            <span v-else-if="part.state === 'output-available'">
                                — {{ outputText(part.output) }}
                            </span>
                            <span v-else-if="part.state === 'output-error'">
                                — {{ t(safeAgentErrorKey(part.errorText)) }}
                            </span>
                        </div>
                    </template>

                    <div
                        v-if="
                            message.role === 'assistant' &&
                            message.parts.length === 0 &&
                            isStreaming
                        "
                        class="flex items-center gap-1 py-1"
                    >
                        <span
                            class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary [animation-delay:-0.3s]"
                        ></span>
                        <span
                            class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary [animation-delay:-0.15s]"
                        ></span>
                        <span
                            class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary"
                        ></span>
                    </div>
                </div>

                <div
                    v-if="message.role === 'user'"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-outline-glass bg-surface-container text-primary"
                >
                    <User :size="16" />
                </div>
            </div>
        </div>

        <div
            v-else
            class="mx-auto flex w-full max-w-2xl flex-1 flex-col items-center justify-center space-y-8 text-center"
        >
            <div class="space-y-4">
                <div
                    class="mb-2 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                >
                    <Sparkles :size="24" />
                </div>
                <h2
                    class="font-heading text-2xl font-black tracking-tight text-on-surface md:text-3xl"
                >
                    {{ t('dashboard.how_can_i_help') }}
                </h2>
                <p class="max-w-md text-xs font-medium text-on-surface-variant">
                    {{ t('dashboard.chat_description') }}
                </p>
            </div>

            <div class="grid w-full gap-3 sm:grid-cols-3">
                <button
                    v-for="key in suggestions"
                    :key="key"
                    type="button"
                    class="flex cursor-pointer flex-col items-start gap-2 rounded-xl border border-outline-glass bg-surface-container-lowest p-4 text-left shadow-sm transition-all hover:border-primary/30"
                    @click="selectSuggestion(key)"
                >
                    <Sparkles class="text-primary" :size="14" />
                    <span
                        class="text-[11px] leading-snug font-semibold text-on-surface"
                    >
                        {{ t(key) }}
                    </span>
                </button>
            </div>
        </div>

        <p
            v-if="error"
            role="alert"
            class="mx-auto mb-2 w-full max-w-3xl text-xs text-error-red"
        >
            {{ safeError }}
        </p>

        <div
            v-if="error || stopped"
            role="status"
            class="mx-auto mb-2 w-full max-w-3xl text-xs"
        >
            <p v-if="stopped">{{ t('agent.stopped') }}</p>
            <button
                type="button"
                class="underline"
                :disabled="busy"
                @click="refreshConversation"
            >
                {{ t('agent.refresh_conversation') }}
            </button>
        </div>
        <p
            v-if="hasPendingApproval"
            class="mx-auto mb-2 w-full max-w-3xl text-[11px] text-on-surface-variant"
        >
            {{ t('agent.approval.prompt_disabled') }}
        </p>

        <form
            class="relative mx-auto mt-auto w-full max-w-3xl"
            @submit.prevent="submitMessage"
        >
            <div
                class="flex items-center rounded-2xl border border-outline-glass bg-surface-container-lowest p-2 shadow-lg transition-all focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20"
            >
                <textarea
                    ref="chatTextarea"
                    v-model="inputMessage"
                    rows="1"
                    :placeholder="t('dashboard.type_message')"
                    :disabled="busy || hasPendingApproval || stopped"
                    class="max-h-32 flex-1 resize-none overflow-y-auto bg-transparent px-3 py-2 text-xs text-on-surface outline-none placeholder:text-on-surface-variant/50 disabled:opacity-60 scrollbar-none"
                    @keydown="onComposerKey"
                    :aria-label="t('dashboard.type_message')"
                ></textarea>

                <button
                    v-if="isStreaming"
                    type="button"
                    :aria-label="t('agent.stop')"
                    class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-error-red text-white"
                    @click="stopResponse"
                >
                    <Square :size="13" fill="currentColor" />
                </button>
                <button
                    v-else
                    type="submit"
                    :disabled="
                        !inputMessage.trim() ||
                        busy ||
                        hasPendingApproval ||
                        stopped
                    "
                    :aria-label="t('dashboard.send')"
                    class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-primary text-white transition-all hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Send :size="14" />
                </button>
            </div>
        </form>
    </div>
</template>
