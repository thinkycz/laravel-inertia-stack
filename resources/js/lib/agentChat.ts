import { isToolUIPart, type UIMessage } from 'ai';
import type { AgentToolDecision, NotificationApprovalArguments } from '@/types';

export function messageText(message: UIMessage): string {
    return message.parts
        .filter((part) => part.type === 'text')
        .map((part) => part.text)
        .join('');
}

export function hasPendingApprovals(messages: UIMessage[]): boolean {
    return messages.some((message) =>
        message.parts.some(
            (part) => isToolUIPart(part) && part.state === 'approval-requested',
        ),
    );
}

export function approveNotification(
    argumentsValue: NotificationApprovalArguments,
): AgentToolDecision {
    return {
        action: 'approve',
        arguments: {
            subject: argumentsValue.subject.trim(),
            message: argumentsValue.message.trim(),
        },
    };
}

export function rejectTool(result: string): AgentToolDecision {
    const normalized = result.trim();

    return {
        action: 'reject',
        ...(normalized ? { result: normalized } : {}),
    };
}

export function canRespondToApproval(
    messages: UIMessage[],
    decisions: Record<string, AgentToolDecision>,
    approvalId: string,
): boolean {
    return (
        !Object.prototype.hasOwnProperty.call(decisions, approvalId) &&
        messages.some((message) =>
            message.parts.some(
                (part) =>
                    isToolUIPart(part) &&
                    part.state === 'approval-requested' &&
                    part.approval.id === approvalId,
            ),
        )
    );
}

export function shouldSendComposerKey(
    event: Pick<KeyboardEvent, 'key' | 'shiftKey' | 'isComposing' | 'keyCode'>,
): boolean {
    return (
        event.key === 'Enter' &&
        !event.shiftKey &&
        !event.isComposing &&
        event.keyCode !== 229
    );
}

export function safeAgentErrorKey(message: string): string {
    try {
        const body: unknown = JSON.parse(message);
        if (
            body &&
            typeof body === 'object' &&
            'message' in body &&
            typeof body.message === 'string'
        )
            message = body.message;
    } catch {
        // SDK stream errors carry the code directly; HTTP errors carry JSON.
    }
    return [
        'ai.errors.provider_failed',
        'agent.error_generic',
        'agent.conversation_busy',
        'agent.approval.invalid',
    ].includes(message)
        ? message
        : 'agent.error_generic';
}
