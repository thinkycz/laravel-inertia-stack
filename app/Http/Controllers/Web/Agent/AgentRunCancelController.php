<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Agent;

use App\Ai\AgentRunService;
use App\Http\Controllers\Web\Concerns\ValidatesWebRequests;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Thinkycz\LaravelCore\Support\Resolver;

class AgentRunCancelController
{
    use ValidatesWebRequests;

    /**
     * Cancel a running background agent run.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = User::mustAuth();
        $runs = Resolver::resolve(AgentRunService::class);

        $validated = $this->validateRequest($request, [
            'run_id' => 'required|string',
        ]);

        $run = $runs->cancel($user, $validated->parseString('run_id'));

        return Resolver::resolveResponseFactory()->json($runs->snapshot($run));
    }
}
