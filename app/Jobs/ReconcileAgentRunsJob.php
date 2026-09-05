<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\AgentRunService;
use App\Models\AgentRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;

class ReconcileAgentRunsJob implements ShouldQueue
{
    /**
     * Reconcile work abandoned by dispatch failures or terminated workers.
     */
    public function handle(AgentRunService $runs): void
    {
        AgentRun::query()->where(function (Builder $query): void {
            $query->where('status', AgentRun::STATUS_QUEUED)->where('created_at', '<', \now()->subMinutes(5));
        })->orWhere(function (Builder $query): void {
            $query->where('status', AgentRun::STATUS_RUNNING)->where('started_at', '<', \now()->subSeconds(150));
        })->each(function (AgentRun $run) use ($runs): void {
            $runs->reconcile($run->getId());
        }, 100);
    }
}
