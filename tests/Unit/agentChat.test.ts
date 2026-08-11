import { describe, expect, it } from 'vitest';
import type { UIMessage } from 'ai';
import {
    approveNotification,
    hasPendingApprovals,
    messageText,
    rejectTool,
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
