<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Ai\AgentRunAlreadyActiveException;
use App\Ai\AgentRunService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\ThrottleSupport;

class AgentRunStartController
{
    use ValidatesWebRequests;

    /**
     * Start a durable background agent run.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $runs = Resolver::resolve(AgentRunService::class);

        $user = User::mustAuth();

        $validated = $this->validateRequest($request, [
            'prompt' => 'required|string',
            'conversation_id' => 'nullable|string',
            'clarification_message_id' => 'nullable|string',
        ]);

        try {
            $result = ThrottleSupport::operation(Limit::perMinute(30)->by('ai:start:' . $user->getKey()), fn(): array => $runs->start(
                $user,
                $validated->parseString('prompt'),
                $validated->parseNullableString('conversation_id'),
                $validated->parseNullableString('clarification_message_id'),
            ));
        } catch (AgentRunAlreadyActiveException $exception) {
            return Resolver::resolveResponseFactory()->json($runs->snapshot($exception->run), 409);
        }

        return Resolver::resolveResponseFactory()->json($runs->snapshot($result['run']));
    }
}
