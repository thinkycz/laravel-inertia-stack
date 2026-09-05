import { describe, expect, test } from 'vitest';
import { parseTextDeltaSseChunk } from '@/lib/sse';

describe('parseTextDeltaSseChunk', () => {
    test('handles chunk-split data rows', () => {
        const first = parseTextDeltaSseChunk('data: {"type":"text_delta","del');
        expect(first.deltas).toEqual([]);
        expect(first.done).toBe(false);

        const second = parseTextDeltaSseChunk('ta":"Hello"}\n\n', first.buffer);
        expect(second.deltas).toEqual(['Hello']);
        expect(second.buffer).toBe('');
        expect(second.eventTypes).toEqual(['text_delta']);
        expect(second.lastEventId).toBeNull();
    });

    test('ignores malformed rows and keeps later valid deltas', () => {
        const parsed = parseTextDeltaSseChunk(
            'data: {nope}\n\ndata: {"type":"text_delta","delta":" world"}\n\n',
        );

        expect(parsed.deltas).toEqual([' world']);
        expect(parsed.done).toBe(false);
    });

    test('keeps text buffered before a tool activity flush', () => {
        const parsed = parseTextDeltaSseChunk(
            'data: {"id":4,"type":"tool_activity","tool_name":"AskClarifyingQuestionsTool","delta":"Let me clarify: "}\n\n',
        );
        expect(parsed.deltas).toEqual(['Let me clarify: ']);
        expect(parsed.toolName).toBe('AskClarifyingQuestionsTool');
        expect(parsed.lastEventId).toBe(4);
    });

    test('detects done sentinel', () => {
        const parsed = parseTextDeltaSseChunk('data: [DONE]\n\n');

        expect(parsed.done).toBe(true);
    });

    test('exposes non-text event types as activity', () => {
        const parsed = parseTextDeltaSseChunk(
            'data: {"type":"stream_start"}\n\ndata: {"type":"tool_call","tool_name":"GetShiftsTool"}\n\n',
        );

        expect(parsed.deltas).toEqual([]);
        expect(parsed.eventTypes).toEqual(['stream_start', 'tool_call']);
    });

    test('tracks replay event ids and terminal run events', () => {
        const parsed = parseTextDeltaSseChunk(
            'data: {"id":12,"type":"text_delta","delta":"Hi"}\n\ndata: {"id":13,"type":"run_completed","status":"completed"}\n\n',
        );

        expect(parsed.deltas).toEqual(['Hi']);
        expect(parsed.lastEventId).toBe(13);
        expect(parsed.terminalType).toBe('run_completed');
    });

    test('preserves validated clarifications and terminal content snapshots', () => {
        const clarification = {
            question: 'Which?',
            options: ['A', 'B'],
            recommended_option: 'A',
        };
        const parsed = parseTextDeltaSseChunk(
            `data: ${JSON.stringify({ id: 2, type: 'tool_activity', clarification })}\n\ndata: {"id":3,"type":"run_cancelled","assistant_content":"partial"}\n\n`,
        );
        expect(parsed.clarification).toEqual(clarification);
        expect(parsed.assistantContent).toBe('partial');
        expect(
            parseTextDeltaSseChunk(
                'data: {"type":"tool_activity","clarification":{"question":"Which?","options":[null]}}\n\n',
            ).clarification,
        ).toBeNull();
    });

    test('exposes run failure errors', () => {
        const parsed = parseTextDeltaSseChunk(
            'data: {"id":2,"type":"run_failed","error":"Provider failed"}\n\n',
        );

        expect(parsed.terminalType).toBe('run_failed');
        expect(parsed.error).toBe('Provider failed');
    });
});
