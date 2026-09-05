<?php

declare(strict_types=1);

use App\Ai\AgentRunAlreadyActiveException;
use App\Ai\AgentRunService;
use App\Ai\Agents\ChatAgent;
use App\Models\AgentRun;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Models\Conversation;
use Symfony\Component\Console\Input\ArrayInput;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\ThrottleSupport;

require __DIR__ . '/bootstrap.php';
$app = require \dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Http::preventStrayRequests();
Notification::fake();
ChatAgent::fake(['Disposable worker answer']);
$runs = Resolver::resolve(AgentRunService::class);
$mode = $argv[1] ?? '';
try {
    if ($mode === 'delivery') {
        ThrottleSupport::delivery('test', $argv[2]);
    } elseif ($mode === 'start') {
        $runs->start(User::query()->findOrFail($argv[2]), 'Concurrent prompt', $argv[3]);
    } elseif ($mode === 'delete') {
        Conversation::query()->whereKey($argv[2])->delete();
    } elseif ($mode === 'flush') {
        $runs->flush($argv[2], 'Partial');
    } elseif ($mode === 'complete') {
        $runs->finish($argv[2], AgentRun::STATUS_COMPLETED, 'Complete');
    } elseif ($mode === 'cancel') {
        $runs->finish($argv[2], AgentRun::STATUS_CANCELLED);
    } else {
        exit($app->make(Kernel::class)->handle(new ArrayInput([
            'command' => 'queue:work', 'connection' => 'redis', '--stop-when-empty' => true,
            '--tries' => 1, '--timeout' => 120, '--sleep' => 0, '--no-interaction' => true,
        ])));
    }
} catch (ThrottleRequestsException) {
    exit(4);
} catch (AgentRunAlreadyActiveException) {
    exit(3);
}
