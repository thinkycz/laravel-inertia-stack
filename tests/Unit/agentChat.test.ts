import { describe, expect, it } from 'vitest';
import type { UIMessage } from 'ai';
import {
    approveNotification,
    hasPendingApprovals,
    messageText,
    rejectTool,
    canRespondToApproval,
    shouldSendComposerKey,
    safeAgentErrorKey,
} from '@/lib/agentChat';

const pendingMessage: UIMessage = {
    id: 'assistant-1',
    role: 'assistant',
    parts: [
        {
            type: 'tool-SendTestNotificationTool',
            toolCallId: 'call-1',
            state: 'approval-requested',
            input: { subject: 'Subject', message: 'Message' },
            approval: { id: 'call-1' },
        },
    ],
};

describe('agent chat protocol helpers', () => {
    it('combines streamed text parts', () => {
        expect(
            messageText({
                id: 'user-1',
                role: 'user',
                parts: [
                    { type: 'text', text: 'Hello ' },
                    { type: 'text', text: 'world' },
                ],
            }),
        ).toBe('Hello world');
    });

    it('detects pending approvals', () => {
        expect(hasPendingApprovals([pendingMessage])).toBe(true);
        expect(hasPendingApprovals([])).toBe(false);
    });

    it('only submits still-pending decisions once', () => {
        expect(canRespondToApproval([pendingMessage], {}, 'call-1')).toBe(true);
        expect(
            canRespondToApproval(
                [pendingMessage],
                { 'call-1': { action: 'approve' } },
                'call-1',
            ),
        ).toBe(false);
        expect(canRespondToApproval([pendingMessage], {}, 'foreign')).toBe(
            false,
        );
        expect(canRespondToApproval([], {}, 'call-1')).toBe(false);
    });
    it('sends Enter but preserves Shift+Enter and IME composition', () => {
        const key = {
            key: 'Enter',
            shiftKey: false,
            isComposing: false,
            keyCode: 13,
        };
        expect(shouldSendComposerKey(key)).toBe(true);
        expect(shouldSendComposerKey({ ...key, shiftKey: true })).toBe(false);
        expect(shouldSendComposerKey({ ...key, isComposing: true })).toBe(
            false,
        );
        expect(shouldSendComposerKey({ ...key, keyCode: 229 })).toBe(false);
    });
    it('only renders recognized safe error codes', () => {
        expect(safeAgentErrorKey('{"message":"agent.conversation_busy"}')).toBe(
            'agent.conversation_busy',
        );
        expect(safeAgentErrorKey('{"message":"secret provider key"}')).toBe(
            'agent.error_generic',
        );
        expect(safeAgentErrorKey('ai.errors.provider_failed')).toBe(
            'ai.errors.provider_failed',
        );
        expect(safeAgentErrorKey('secret provider api key')).toBe(
            'agent.error_generic',
        );
        expect(safeAgentErrorKey('<html>server internals</html>')).toBe(
            'agent.error_generic',
        );
    });

    it('builds edited approval and optional rejection payloads', () => {
        expect(
            approveNotification({
                subject: '  Edited subject  ',
                message: ' Edited message ',
            }),
        ).toEqual({
            action: 'approve',
            arguments: {
                subject: 'Edited subject',
                message: 'Edited message',
            },
        });
        expect(rejectTool('  Not now  ')).toEqual({
            action: 'reject',
            result: 'Not now',
        });
        expect(rejectTool('')).toEqual({ action: 'reject' });
    });
});
