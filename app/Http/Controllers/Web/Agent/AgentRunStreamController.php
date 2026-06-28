<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Ai\AgentRunService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\AgentRun;
use App\Models\AgentRunEvent;
use App\Models\User;
use Generator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentRunStreamController
{
    use ValidatesWebRequests;

    private const int POLL_MICROSECONDS = 250000;

    private const int STREAM_SECONDS = 25;

    /**
     * Replay stored run events and briefly wait for fresh ones.
     */
    public function __invoke(Request $request, AgentRunService $runs): StreamedResponse
    {
        $user = User::mustAuth();

        $validated = $this->validateRequest($request, [
            'run_id' => 'required|string',
            'after_event_id' => 'nullable|integer',
        ]);

        $run = $runs->ownedRun($user, $validated->parseString('run_id'));
        $afterEventId = $validated->parseNullableInt('after_event_id') ?? 0;

        return \response()->stream(function () use ($run, $afterEventId): Generator {
            $lastEventId = $afterEventId;
            $deadline = \microtime(true) + self::STREAM_SECONDS;

            while ($deadline > \microtime(true)) {
                $events = AgentRunEvent::query()
                    ->where('run_id', $run->getId())
                    ->where('id', '>', $lastEventId)
                    ->orderBy('id')
                    ->limit(100)
                    ->get();

                foreach ($events as $event) {
                    $lastEventId = $event->getId();
                    yield $this->sseEvent($event);
                }

                $freshRun = AgentRun::query()->where('id', $run->getId())->first();
                if ($freshRun instanceof AgentRun && !$freshRun->isActive()) {
                    break;
                }

                if ($events->isEmpty()) {
                    \usleep(self::POLL_MICROSECONDS);
                }
            }

            yield "data: [DONE]\n\n";
        }, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Format a persisted run event as an SSE row.
     */
    private function sseEvent(AgentRunEvent $event): string
    {
        return 'data: ' . \json_encode([
            'id' => $event->getId(),
            'type' => $event->getType(),
            ...$event->getPayload(),
        ], \JSON_THROW_ON_ERROR) . "\n\n";
    }
}
