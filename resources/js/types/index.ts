export interface AuthUser {
    id: number;
    email: string;
    locale: string;
    email_verified_at: string | null;
}

export interface AppMeta {
    name: string;
    locale: string;
    locales: string[];
}

export interface FlashProps {
    success: string | null;
    error: string | null;
}

export interface ConversationItem {
    id: string;
    title: string;
    updated_at: string;
}

/**
 * A single chat message as serialized by `ConversationRepository`.
 *
 * `role` is intentionally a string (not a narrow union) because the
 * AI SDK may emit values such as `system` or `tool` that the frontend
 * does not branch on. `content` is nullable because persisted rows
 * may carry a `null` content (e.g. tool-call placeholders).
 */
export interface ChatMessage {
    id: string;
    status?: string;
    clarification?: AgentClarification | null;
    clarification_answer?: string | null;
    role: string;
    content: string | null;
}

/**
 * The conversation payload rendered on the dashboard.
 */
export interface ChatConversation {
    id: string;
    title: string;
    messages: ChatMessage[];
}

/**
 * A multiple-choice clarification produced by the
 * AskClarifyingQuestionsTool. `recommended_option` is the option text
 * the agent recommends (not its index) and may be null when the agent
 * does not express a preference.
 */
export interface AgentClarification {
    question: string;
    options: string[];
    recommended_option: string | null;
}

/**
 * A snapshot of a background agent run, as serialized by
 * `AgentRunService::serializeActiveRun()` and shared with the dashboard
 * via Inertia. `last_event_id` lets the bridge resume polling without
 * replaying events the client has already seen.
 */
export interface AgentRunSnapshot {
    assistant_message_id: string;
    id: string;
    conversation_id: string;
    status: 'queued' | 'running' | 'completed' | 'failed' | 'cancelled';
    assistant_content: string;
    last_event_id: number | null;
    error: string | null;
}

export interface SharedProps {
    [key: string]: unknown;

    app: AppMeta;
    auth: {
        user: AuthUser | null;
    };
    conversations: ConversationItem[];
    flash: FlashProps;
    errors: Record<string, string>;
}
