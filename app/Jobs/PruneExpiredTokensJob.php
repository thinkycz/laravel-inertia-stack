<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Thinkycz\LaravelCore\Models\DatabaseToken;

class PruneExpiredTokensJob implements ShouldQueue
{
    use Queueable;

    /**
     * Dispatch bounded per-session work after committing the current batch.
     */
    public function handle(): void
    {
        foreach (DatabaseToken::query()->where('expires_at', '<=', Carbon::now())->lazyById(100) as $token) {
            DB::transaction(static function () use ($token): void {
                PruneExpiredTokenJob::dispatch($token->getKey())->afterCommit();
            });
        }
    }
}
