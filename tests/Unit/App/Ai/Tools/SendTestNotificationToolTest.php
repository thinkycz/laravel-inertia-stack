<?php

declare(strict_types=1);

use App\Ai\Tools\SendTestNotificationTool;
use App\Models\User;
use App\Notifications\AgentTestNotification;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Notification;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Typer;

\test('test notification tool always requires approval', function (): void {
    $tool = new SendTestNotificationTool(UserFactory::new()->createOne());
    $approval = $tool->shouldRequestApproval(new Request([
        'subject' => 'A subject',
        'message' => 'A message',
    ]));

    \expect($approval)->not->toBeNull();
});

\test('approved test notification uses the explicit participant instead of mutable guard state', function (): void {
    Notification::fake();

    $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $otherUser = Typer::assertInstance(UserFactory::new()->createOne(), User::class);
    $this->be($otherUser, 'users');

    $result = (new SendTestNotificationTool($user))->handle(new Request([
        'subject' => 'Edited subject',
        'message' => 'Edited message',
    ], 'tool-call-1'));

    \expect($result)->toBe('The test notification was queued.');

    Notification::assertSentTo($user, AgentTestNotification::class, function (AgentTestNotification $notification): bool {
        return $notification->subject === 'Edited subject' &&
            $notification->message === 'Edited message';
    });
    Notification::assertNotSentTo($otherUser, AgentTestNotification::class);
});
