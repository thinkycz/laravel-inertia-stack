<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Thinkycz\LaravelCore\Models\DatabaseToken;

class PruneExpiredTokenJob implements ShouldQueue
{
    use Queueable;

    /**
     * Delete one expired session, rechecking expiry at execution time.
     */
    public function __construct(public int $tokenId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DatabaseToken::query()->whereKey($this->tokenId)->where('expires_at', '<=', Carbon::now())->delete();
    }
}
