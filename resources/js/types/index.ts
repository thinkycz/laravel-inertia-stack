import type { UIMessage } from 'ai';

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
    updated_at: string | null;
}

/**
 * The conversation payload rendered on the dashboard.
 */
export interface ChatConversation {
    id: string;
    title: string;
    messages: UIMessage[];
}

/**
 * Editable arguments for the approved test-notification tool.
 */
export interface NotificationApprovalArguments {
    subject: string;
    message: string;
}

/**
 * Approval continuation submitted to the Laravel AI SDK.
 */
export interface AgentToolDecision {
    action: 'approve' | 'reject';
    arguments?: NotificationApprovalArguments;
    result?: string;
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
