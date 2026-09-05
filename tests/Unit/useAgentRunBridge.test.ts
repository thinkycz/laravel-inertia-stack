import { afterEach, beforeEach, describe, expect, test, vi } from 'vitest';
import { effectScope, nextTick, type EffectScope } from 'vue';
import {
    useAgentRunBridge,
    type AgentRunBridge,
    type AgentRunBridgeOptions,
} from '@/composables/useAgentRunBridge';
import type { AgentRunSnapshot } from '@/types';

interface BridgeCallLog {
    onRunStarted: ReturnType<typeof vi.fn>;
    onTextDelta: ReturnType<typeof vi.fn>;
    onToolActivity: ReturnType<typeof vi.fn>;
    onTerminal: ReturnType<typeof vi.fn>;
    onError: ReturnType<typeof vi.fn>;
}

function makeSnapshot(
    overrides: Partial<AgentRunSnapshot> = {},
): AgentRunSnapshot {
    return {
        id: 'run-123',
        assistant_message_id: 'assistant-123',
        conversation_id: 'conv-456',
        status: 'queued',
        assistant_content: '',
        last_event_id: 0,
        error: null,
        ...overrides,
    };
}

interface MountedBridge {
    scope: EffectScope;
    bridge: AgentRunBridge;
    calls: BridgeCallLog;
    stop: () => void;
}

function mountBridge(
    options: Partial<AgentRunBridgeOptions> = {},
): MountedBridge {
    const calls: BridgeCallLog = {
        onRunStarted: vi.fn(),
        onTextDelta: vi.fn(),
        onToolActivity: vi.fn(),
        onTerminal: vi.fn(),
        onError: vi.fn(),
    };

    const scope = effectScope();
    let bridge: AgentRunBridge | null = null;

    scope.run(() => {
        bridge = useAgentRunBridge({
            initialRun: null,
            onRunStarted: calls.onRunStarted,
            onTextDelta: calls.onTextDelta,
            onToolActivity: calls.onToolActivity,
            onTerminal: calls.onTerminal,
            onError: calls.onError,
            ...options,
        });
    });

    return {
        scope,
        bridge: bridge as unknown as AgentRunBridge,
        calls,
        stop: () => {
            scope.stop();
        },
    };
}

/**
 * Build a fake SSE response body. The last chunk is always
 * `data: [DONE]\n\n` plus a terminal event so the bridge's poll loop
 * exits cleanly via `onTerminal()` and never retries with an extra fetch.
 *
 * Note: the `Response` constructor requires the body to be a real
 * `ReadableStream` (or one of the other valid `BodyInit` types). A plain
 * object that mimics the stream API silently coerces to an empty body
 * and triggers the bridge's empty-body retry path — so we use a real
 * `ReadableStream` here.
 */
function makeStreamBody(events: string[]): ReadableStream<Uint8Array> {
    const encoder = new TextEncoder();
    const chunks: string[] = [
        ...events.map((e) => `data: ${e}\n\n`),
        'data: [DONE]\n\n',
    ];

    return new ReadableStream<Uint8Array>({
        start(controller) {
            for (const chunk of chunks) {
                controller.enqueue(encoder.encode(chunk));
            }
            controller.close();
        },
    });
}

function sseResponse(events: string[]): Response {
    return new Response(makeStreamBody(events), {
        status: 200,
        headers: { 'Content-Type': 'text/event-stream' },
    });
}

function startResponse(
    runId: string,
    conversationId: string,
    status = 'queued',
): Response {
    return new Response(
        JSON.stringify({
            run_id: runId,
            conversation_id: conversationId,
            status,
        }),
        { status: 200 },
    );
}

const flushMicrotasks = (): Promise<void> =>
    new Promise((resolve) => setTimeout(resolve, 0));

describe('useAgentRunBridge', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('document', {
            cookie: 'XSRF-TOKEN=test',
        });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    test('start() happy path sets activeRun, fires onRunStarted, and streams text deltas through to the terminal', async () => {
        const mounted = mountBridge();

        fetchMock
            .mockResolvedValueOnce(startResponse('run-1', 'conv-1'))
            .mockResolvedValueOnce(
                sseResponse([
                    '{"id":1,"type":"text_delta","delta":"Hi"}',
                    '{"id":2,"type":"run_completed"}',
                ]),
            );

        const started = await mounted.bridge.start({
            prompt: 'hello',
            conversationId: null,
        });

        expect(started.id).toBe('run-1');
        expect(started.conversation_id).toBe('conv-1');
        expect(mounted.bridge.activeRun.value?.id).toBe('run-1');
        expect(mounted.calls.onRunStarted).toHaveBeenCalledWith(started);

        await flushMicrotasks();
        await nextTick();

        expect(mounted.calls.onTextDelta).toHaveBeenCalledWith('Hi');
        expect(mounted.calls.onTerminal).toHaveBeenCalledWith(
            'completed',
            null,
        );
        expect(mounted.bridge.isPolling.value).toBe(false);

        mounted.stop();
    });

    test('start() with an existing run on the server resumes polling from the existing run', async () => {
        const mounted = mountBridge();

        fetchMock
            .mockResolvedValueOnce(
                startResponse('run-existing', 'conv-1', 'running'),
            )
            .mockResolvedValueOnce(
                sseResponse([
                    '{"id":42,"type":"text_delta","delta":"cont"}',
                    '{"id":43,"type":"run_completed"}',
                ]),
            );

        await mounted.bridge.start({
            prompt: 'second',
            conversationId: 'conv-1',
        });
        await flushMicrotasks();

        const streamCall = fetchMock.mock.calls[1];
        const url = String(streamCall[0]);
        expect(url).toContain('run_id=run-existing');
        expect(url).toContain('after_event_id=0');
        expect(mounted.calls.onTextDelta).toHaveBeenCalledWith('cont');
        expect(mounted.calls.onTerminal).toHaveBeenCalledWith(
            'completed',
            null,
        );

        mounted.stop();
    });

    test('the run_started event advances queued server state independently of connection state', async () => {
        fetchMock.mockResolvedValueOnce(
            new Response(
                new ReadableStream<Uint8Array>({
                    start(controller) {
                        controller.enqueue(
                            new TextEncoder().encode(
                                'data: {"id":1,"type":"run_started","status":"running"}\n\n',
                            ),
                        );
                    },
                }),
            ),
        );
        const mounted = mountBridge({ initialRun: makeSnapshot() });
        await flushMicrotasks();
        expect(mounted.bridge.activeRun.value?.status).toBe('running');
        expect(mounted.bridge.connectionStatus.value).toBe('connected');
        mounted.stop();
    });

    test('poll() propagates tool_activity tool_name to onToolActivity', async () => {
        const mounted = mountBridge();

        fetchMock
            .mockResolvedValueOnce(startResponse('run-2', 'conv-2'))
            .mockResolvedValueOnce(
                sseResponse([
                    '{"id":1,"type":"tool_activity","stream_type":"tool_call","tool_name":"AskClarifyingQuestionsTool"}',
                    '{"id":2,"type":"run_completed"}',
                ]),
            );

        await mounted.bridge.start({ prompt: 'hi', conversationId: null });
        await flushMicrotasks();

        expect(mounted.calls.onToolActivity).toHaveBeenCalledWith(
            'AskClarifyingQuestionsTool',
        );

        mounted.stop();
    });

    test('cancelling a completed run cannot change its terminal state', async () => {
        const mounted = mountBridge({
            initialRun: makeSnapshot({ status: 'completed' }),
        });
        await mounted.bridge.cancel();
        expect(mounted.bridge.activeRun.value?.status).toBe('completed');
        expect(fetchMock).not.toHaveBeenCalled();
        mounted.stop();
    });

    test('failed cancellation preserves the running state and remains retryable', async () => {
        fetchMock.mockImplementation((url: string) =>
            url.includes('/cancel')
                ? Promise.resolve(new Response('{}', { status: 500 }))
                : new Promise(() => {}),
        );
        const mounted = mountBridge({
            initialRun: makeSnapshot({ status: 'running' }),
        });
        await mounted.bridge.cancel();
        expect(mounted.bridge.activeRun.value?.status).toBe('running');
        expect(mounted.calls.onError).toHaveBeenCalledWith(
            'agent.cancel_failed',
        );
        mounted.stop();
    });

    test('start() propagates HTTP error as a thrown error', async () => {
        const mounted = mountBridge();

        fetchMock.mockResolvedValueOnce(
            new Response('forbidden', { status: 403 }),
        );

        await expect(
            mounted.bridge.start({ prompt: 'hi', conversationId: null }),
        ).rejects.toThrow('agent.error_generic');

        mounted.stop();
    });

    test('auto-resume: initialRun that is queued triggers polling on mount', async () => {
        fetchMock.mockResolvedValueOnce(
            sseResponse([
                '{"id":10,"type":"text_delta","delta":"resumed"}',
                '{"id":11,"type":"run_completed"}',
            ]),
        );

        const initialRun = makeSnapshot({
            id: 'run-resume',
            status: 'running',
            last_event_id: 7,
        });

        const mounted = mountBridge({ initialRun });

        await flushMicrotasks();
        await nextTick();

        const url = String(fetchMock.mock.calls[0][0]);
        expect(url).toContain('run_id=run-resume');
        expect(url).toContain('after_event_id=7');
        expect(mounted.calls.onTextDelta).toHaveBeenCalledWith('resumed');
        expect(mounted.calls.onTerminal).toHaveBeenCalledWith(
            'completed',
            null,
        );

        mounted.stop();
    });

    test('conflicts attach the returned snapshot and resume from its cursor', async () => {
        const run = makeSnapshot({
            id: 'existing',
            status: 'running',
            assistant_content: 'Already',
            last_event_id: 8,
        });
        fetchMock
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ active_run: run }), {
                    status: 409,
                }),
            )
            .mockResolvedValueOnce(
                sseResponse([
                    '{"id":9,"type":"text_delta","delta":" done"}',
                    '{"id":10,"type":"run_completed"}',
                ]),
            );
        const mounted = mountBridge();
        await mounted.bridge.start({
            prompt: 'duplicate',
            conversationId: 'conv-456',
        });
        await flushMicrotasks();
        expect(String(fetchMock.mock.calls[1][0])).toContain(
            'after_event_id=8',
        );
        expect(mounted.bridge.activeRun.value?.assistant_content).toBe(
            'Already done',
        );
        mounted.stop();
    });

    test('navigation discards callbacks from an obsolete subscription', async () => {
        let resolveOld: (value: Response) => void = () => {};
        fetchMock
            .mockImplementationOnce(
                () =>
                    new Promise<Response>((resolve) => {
                        resolveOld = resolve;
                    }),
            )
            .mockResolvedValueOnce(
                sseResponse([
                    '{"id":2,"type":"text_delta","delta":"new"}',
                    '{"id":3,"type":"run_completed"}',
                ]),
            );
        const mounted = mountBridge({ initialRun: makeSnapshot() });
        mounted.bridge.attach(makeSnapshot({ id: 'new-run' }));
        resolveOld(
            sseResponse([
                '{"id":2,"type":"text_delta","delta":"obsolete"}',
                '{"id":3,"type":"run_completed"}',
            ]),
        );
        await flushMicrotasks();
        expect(mounted.calls.onTextDelta).toHaveBeenCalledExactlyOnceWith(
            'new',
        );
        expect(mounted.bridge.activeRun.value?.id).toBe('new-run');
        mounted.stop();
    });

    test('repeated empty streams exhaust retries without declaring completion and can retry', async () => {
        vi.useFakeTimers();
        fetchMock.mockResolvedValue(sseResponse([]));
        const mounted = mountBridge({
            initialRun: makeSnapshot({ status: 'running' }),
        });
        await vi.runAllTimersAsync();
        expect(fetchMock).toHaveBeenCalledTimes(6);
        expect(mounted.bridge.connectionStatus.value).toBe('disconnected');
        expect(mounted.bridge.activeRun.value?.status).toBe('running');
        expect(mounted.calls.onTerminal).not.toHaveBeenCalled();
        fetchMock.mockResolvedValueOnce(
            sseResponse(['{"id":1,"type":"run_completed"}']),
        );
        mounted.bridge.retry();
        await vi.runAllTimersAsync();
        expect(mounted.bridge.activeRun.value?.status).toBe('completed');
        mounted.stop();
        vi.useRealTimers();
    });

    test('cancel waits for acknowledgment and preserves a winning completion', async () => {
        let acknowledge: (value: Response) => void = () => {};
        fetchMock.mockImplementation((url: string) =>
            url.includes('/cancel')
                ? new Promise<Response>((resolve) => {
                      acknowledge = resolve;
                  })
                : new Promise(() => {}),
        );
        const mounted = mountBridge({
            initialRun: makeSnapshot({ status: 'running' }),
        });
        const cancellation = mounted.bridge.cancel();
        expect(mounted.bridge.cancelling.value).toBe(true);
        expect(mounted.bridge.activeRun.value?.status).toBe('running');
        acknowledge(new Response(JSON.stringify({ status: 'completed' })));
        await cancellation;
        expect(mounted.bridge.activeRun.value?.status).toBe('completed');
        expect(mounted.bridge.cancelling.value).toBe(false);
        expect(mounted.calls.onTerminal).toHaveBeenCalledWith(
            'completed',
            null,
        );
        mounted.stop();
    });

    test('auto-resume: completed initialRun does NOT trigger polling', async () => {
        const initialRun = makeSnapshot({ status: 'completed' });
        const mounted = mountBridge({ initialRun });

        await flushMicrotasks();

        expect(fetchMock).not.toHaveBeenCalled();

        mounted.stop();
    });
});
