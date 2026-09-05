<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Ai;
use Laravel\Ai\Gateway\FakeTextGateway;
use Symfony\Component\Console\Input\ArrayInput;
use Thinkycz\LaravelCore\Models\DatabaseToken;
use Thinkycz\LaravelCore\Support\ThrottleSupport;

require __DIR__ . '/bootstrap.php';
$app = require \dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Http::preventStrayRequests();
Mail::fake();
Ai::textProvider()->useTextGateway(new FakeTextGateway(static function (): string {
    \usleep(500_000);

    return 'Disposable approval continuation';
}));
$mode = $argv[1] ?? '';
try {
    if ($mode === 'delivery') {
        ThrottleSupport::delivery('test', $argv[2]);
    } elseif ($mode === 'chat' || $mode === 'delete') {
        $user = User::query()->findOrFail($argv[2]);
        $bearer = DatabaseToken::inject()->login('users', $user)->bearer;
        $request = Request::create($mode === 'chat' ? '/agent/chat' : '/conversations/' . $argv[3], $mode === 'chat' ? 'POST' : 'DELETE', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . $bearer], $mode === 'chat' ? $argv[3] : '{}');
        $kernel = $app->make(HttpKernel::class);
        $response = $kernel->handle($request);
        \ob_start();
        $response->sendContent();
        \ob_end_clean();
        $kernel->terminate($request, $response);
        $status = $response->getStatusCode();
        if ($status >= 400) {
            \fwrite(\STDERR, 'HTTP ' . $status . '\\n');
        }
        if ($status === 302 && $mode === 'chat') { \fwrite(\STDERR, 'Unexpected chat redirect: ' . $response->headers->get('Location'));
            exit(2); }
        exit(match ($status) { 200, 204, 302 => 0, 409 => 3, 404 => 5, default => 2 });
    } else {
        exit($app->make(Kernel::class)->handle(new ArrayInput([
            'command' => 'queue:work', 'connection' => 'redis', '--stop-when-empty' => true,
            '--tries' => 1, '--timeout' => 120, '--sleep' => 0, '--no-interaction' => true,
        ])));
    }
} catch (ThrottleRequestsException) {
    exit(4);
}
