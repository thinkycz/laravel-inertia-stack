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
