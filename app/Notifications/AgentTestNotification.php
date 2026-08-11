<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AgentTestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a queued notification that is dispatched after commit.
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $message,
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification delivery channels.
     *
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the localized mail notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject($this->subject)
            ->line($this->message);
    }
}
