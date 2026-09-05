import { onScopeDispose, ref, type Ref } from 'vue';
import type { AgentClarification, AgentRunSnapshot } from '@/types';
import { parseTextDeltaSseChunk } from '@/lib/sse';

export type RunTerminalStatus = 'completed' | 'failed' | 'cancelled';
export type ConnectionStatus =
    'idle' | 'connecting' | 'connected' | 'reconnecting' | 'disconnected';
export interface AgentRunBridgeOptions {
    initialRun: AgentRunSnapshot | null;
    onTextDelta: (delta: string) => void;
    onToolActivity: (tool: string | null) => void;
    onClarification?: (clarification: AgentClarification) => void;
    onTerminal: (status: RunTerminalStatus, error: string | null) => void;
    onRunStarted: (run: AgentRunSnapshot) => void;
    onError: (message: string) => void;
}
interface StartParameters {
    prompt: string;
    conversationId: string | null;
    clarificationMessageId?: string;
}
export interface AgentRunBridge {
    activeRun: Ref<AgentRunSnapshot | null>;
    isPolling: Ref<boolean>;
    connectionStatus: Ref<ConnectionStatus>;
    cancelling: Ref<boolean>;
    attach: (run: AgentRunSnapshot | null) => void;
    retry: () => void;
    start: (params: StartParameters) => Promise<AgentRunSnapshot>;
    cancel: () => Promise<void>;
}
function headers(): Record<string, string> {
    const cookie =
        typeof document === 'undefined'
            ? ''
            : document.cookie
                  .split('; ')
                  .find((row) => row.startsWith('XSRF-TOKEN='));
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-XSRF-TOKEN': cookie ? decodeURIComponent(cookie.slice(11)) : '',
    };
}
function active(run: AgentRunSnapshot | null): boolean {
    return run?.status === 'queued' || run?.status === 'running';
}
function delay(ms: number, signal: AbortSignal): Promise<void> {
    return new Promise((resolve) => {
        const done = (): void => {
            clearTimeout(timer);
            signal.removeEventListener('abort', done);
            resolve();
        };
        const timer = setTimeout(done, ms);
        signal.addEventListener('abort', done, { once: true });
    });
}
export function useAgentRunBridge(
    options: AgentRunBridgeOptions,
): AgentRunBridge {
    const activeRun = ref<AgentRunSnapshot | null>(null);
    const isPolling = ref(false);
    const connectionStatus = ref<ConnectionStatus>('idle');
    const cancelling = ref(false);
    let generation = 0;
    let controller: AbortController | null = null;
    function reset(): number {
        controller?.abort();
        controller = new AbortController();
        cancelling.value = false;
        return ++generation;
    }
    function terminal(run: AgentRunSnapshot): void {
        activeRun.value = run;
        connectionStatus.value = 'idle';
        if (!active(run))
            options.onTerminal(run.status as RunTerminalStatus, run.error);
    }
    async function poll(version: number): Promise<void> {
        const abort = controller!;
        isPolling.value = true;
        let retries = 0;
        try {
            while (
                version === generation &&
                !abort.signal.aborted &&
                active(activeRun.value)
            ) {
                const run = activeRun.value!;
                connectionStatus.value = retries
                    ? 'reconnecting'
                    : 'connecting';
                try {
                    const response = await fetch(
                        `/agent/runs/stream?run_id=${encodeURIComponent(run.id)}&after_event_id=${run.last_event_id ?? 0}`,
                        {
                            signal: abort.signal,
                            credentials: 'same-origin',
                            headers: {
                                ...headers(),
                                Accept: 'text/event-stream',
                            },
                        },
                    );
                    if (version !== generation) return;
                    if (!response.ok || !response.body)
                        throw new Error(
                            response.status === 404
                                ? 'agent.run_unavailable'
                                : 'agent.connection_lost',
                        );
                    connectionStatus.value = 'connected';
                    const reader = response.body.getReader();
                    const stopReader = (): void => {
                        void reader.cancel().catch(() => {});
                    };
                    abort.signal.addEventListener('abort', stopReader, {
                        once: true,
                    });
                    let buffer = '';
                    const decoder = new TextDecoder();
                    try {
                        while (
                            version === generation &&
                            !abort.signal.aborted
                        ) {
                            const chunk = await reader.read();
                            if (version !== generation || abort.signal.aborted)
                                return;
                            if (chunk.done) break;
                            const parsed = parseTextDeltaSseChunk(
                                decoder.decode(chunk.value, { stream: true }),
                                buffer,
                            );
                            buffer = parsed.buffer;
                            if (
                                parsed.eventTypes.includes('run_started') &&
                                activeRun.value?.status === 'queued'
                            )
                                activeRun.value.status = 'running';
                            if (
                                parsed.lastEventId !== null &&
                                parsed.lastEventId >
                                    (activeRun.value?.last_event_id ?? 0)
                            )
                                retries = 0;
                            for (const delta of parsed.deltas) {
                                activeRun.value!.assistant_content += delta;
                                options.onTextDelta(delta);
                            }
                            if (parsed.lastEventId !== null)
                                activeRun.value!.last_event_id =
                                    parsed.lastEventId;
                            if (parsed.toolName !== null)
                                options.onToolActivity(parsed.toolName);
                            if (parsed.clarification)
                                options.onClarification?.(parsed.clarification);
                            if (parsed.terminalType) {
                                terminal({
                                    ...activeRun.value!,
                                    status: parsed.terminalType.slice(
                                        4,
                                    ) as RunTerminalStatus,
                                    assistant_content:
                                        parsed.assistantContent ??
                                        activeRun.value!.assistant_content,
                                    error: parsed.error,
                                });
                                return;
                            }
                            if (parsed.done) break;
                        }
                    } finally {
                        abort.signal.removeEventListener('abort', stopReader);
                        await reader.cancel().catch(() => {});
                        reader.releaseLock();
                    }
                    throw new Error('agent.connection_lost');
                } catch (error) {
                    if (version !== generation || abort.signal.aborted) return;
                    const key =
                        error instanceof Error &&
                        error.message === 'agent.run_unavailable'
                            ? error.message
                            : 'agent.connection_lost';
                    if (key === 'agent.run_unavailable' || retries >= 5) {
                        connectionStatus.value = 'disconnected';
                        options.onError(key);
                        return;
                    }
                    connectionStatus.value = 'reconnecting';
                    await delay(
                        Math.min(1000 * 2 ** retries++, 8000),
                        abort.signal,
                    );
                }
            }
        } finally {
            if (version === generation) isPolling.value = false;
        }
    }
    function attach(run: AgentRunSnapshot | null): void {
        const version = reset();
        activeRun.value = run ? { ...run } : null;
        connectionStatus.value = 'idle';
        isPolling.value = false;
        if (active(run)) void poll(version);
    }
    async function start(params: StartParameters): Promise<AgentRunSnapshot> {
        const version = reset();
        const response = await fetch('/agent/runs', {
            method: 'POST',
            signal: controller!.signal,
            credentials: 'same-origin',
            headers: headers(),
            body: JSON.stringify({
                prompt: params.prompt,
                conversation_id: params.conversationId,
                clarification_message_id: params.clarificationMessageId,
            }),
        });
        if (version !== generation)
            throw new DOMException('Aborted', 'AbortError');
        if (!response.ok && response.status !== 409)
            throw new Error('agent.error_generic');
        const data = (await response.json()) as AgentRunSnapshot & {
            run_id: string;
            active_run?: AgentRunSnapshot;
        };
        if (version !== generation)
            throw new DOMException('Aborted', 'AbortError');
        const snapshot = data.active_run ?? data;
        const run: AgentRunSnapshot = {
            ...snapshot,
            id: snapshot.id ?? data.run_id,
            assistant_message_id: snapshot.assistant_message_id ?? '',
            assistant_content: snapshot.assistant_content ?? '',
            last_event_id: snapshot.last_event_id ?? 0,
            error: snapshot.error ?? null,
        };
        if (!run.id || !run.conversation_id)
            throw new Error('agent.error_generic');
        activeRun.value = run;
        options.onRunStarted(run);
        if (active(run)) void poll(version);
        else terminal(run);
        return run;
    }
    async function cancel(): Promise<void> {
        const run = activeRun.value;
        if (!active(run) || !run || cancelling.value) return;
        const version = generation;
        cancelling.value = true;
        try {
            const response = await fetch('/agent/runs/cancel', {
                method: 'POST',
                signal: controller!.signal,
                credentials: 'same-origin',
                headers: headers(),
                body: JSON.stringify({ run_id: run.id }),
            });
            if (version !== generation) return;
            if (!response.ok) throw new Error('agent.cancel_failed');
            const data = (await response.json()) as Partial<AgentRunSnapshot>;
            if (version !== generation) return;
            if (!data.status) throw new Error('agent.cancel_failed');
            const acknowledged = { ...activeRun.value!, ...data };
            if (!active(acknowledged)) {
                reset();
                isPolling.value = false;
                terminal(acknowledged);
            }
        } catch {
            if (version === generation) options.onError('agent.cancel_failed');
        } finally {
            if (version === generation) cancelling.value = false;
        }
    }
    onScopeDispose(() => {
        reset();
        controller?.abort();
    });
    attach(options.initialRun);
    return {
        activeRun,
        isPolling,
        connectionStatus,
        cancelling,
        attach,
        retry: () => attach(activeRun.value),
        start,
        cancel,
    };
}
