import type { AgentClarification } from '@/types';
export interface SseParseResult {
    buffer: string;
    deltas: string[];
    done: boolean;
    eventTypes: string[];
    lastEventId: number | null;
    terminalType: string | null;
    error: string | null;
    toolName: string | null;
    clarification: AgentClarification | null;
    assistantContent: string | null;
}

export function parseTextDeltaSseChunk(
    chunk: string,
    previousBuffer = '',
): SseParseResult {
    const combined = previousBuffer + chunk;
    const lines = combined.split(/\r?\n/);
    const buffer =
        combined.endsWith('\n') || combined.endsWith('\r')
            ? ''
            : (lines.pop() ?? '');
    const deltas: string[] = [];
    const eventTypes: string[] = [];
    let lastEventId: number | null = null;
    let terminalType: string | null = null;
    let error: string | null = null;
    let toolName: string | null = null;
    let done = false;
    let clarification: AgentClarification | null = null;
    let assistantContent: string | null = null;

    for (const line of lines) {
        const trimmed = line.trim();

        if (!trimmed.startsWith('data: ')) {
            continue;
        }

        const data = trimmed.slice(6);

        if (data === '[DONE]') {
            done = true;
            continue;
        }

        try {
            const parsed = JSON.parse(data) as {
                type?: unknown;
                delta?: unknown;
                clarification?: AgentClarification;
                assistant_content?: unknown;
            };

            if (typeof parsed.type === 'string') {
                eventTypes.push(parsed.type);

                if (
                    parsed.type === 'run_completed' ||
                    parsed.type === 'run_failed' ||
                    parsed.type === 'run_cancelled'
                ) {
                    terminalType = parsed.type;
                    if (typeof parsed.assistant_content === 'string')
                        assistantContent = parsed.assistant_content;
                }
            }

            if (typeof (parsed as { id?: unknown }).id === 'number') {
                lastEventId = (parsed as { id: number }).id;
            }

            if (
                (parsed.type === 'text_delta' ||
                    parsed.type === 'tool_activity') &&
                typeof parsed.delta === 'string'
            ) {
                deltas.push(parsed.delta);
            }

            if (
                parsed.type === 'run_failed' &&
                typeof (parsed as { error?: unknown }).error === 'string'
            ) {
                error = (parsed as { error: string }).error;
            }

            if (
                parsed.clarification &&
                typeof parsed.clarification.question === 'string' &&
                Array.isArray(parsed.clarification.options) &&
                parsed.clarification.options.every(
                    (option) => typeof option === 'string',
                )
            )
                clarification = parsed.clarification;

            if (parsed.type === 'tool_activity') {
                const candidate = (parsed as { tool_name?: unknown }).tool_name;
                if (typeof candidate === 'string') {
                    toolName = candidate;
                }
            }
        } catch {
            // Ignore malformed SSE rows and continue parsing later complete rows.
        }
    }

    return {
        buffer,
        deltas,
        done,
        eventTypes,
        lastEventId,
        terminalType,
        error,
        toolName,
        clarification,
        assistantContent,
    };
}
