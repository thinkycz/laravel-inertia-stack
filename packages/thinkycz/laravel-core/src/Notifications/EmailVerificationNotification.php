<?php

declare(strict_types=1);

namespace Thinkycz\LaravelCore\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue as ShouldQueueContract;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Trans;
use Thinkycz\LaravelCore\Support\Typer;

class EmailVerificationNotification extends Notification implements ShouldQueueContract
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected function __construct(
        protected string $guardName,
        protected string|null $token = null,
        protected string|null $email = null,
        protected string|null $spa = null,
        protected string|null $url = null,
    ) {
        $this->afterCommit();
    }

    /**
     * Inject.
     */
    public static function inject(string $guardName, string|null $token = null, string|null $email = null, string|null $spa = null, string|null $url = null): self
    {
        return new self($guardName, $token, $email, $spa, $url);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $trans = Trans::inject();

        return (new MailMessage())
            ->subject($trans->assertString('Verify Email Address'))
            ->line($trans->assertString('Please click the button below to verify your email address.'))
            ->action($trans->assertString('Verify Email Address'), $this->getUrl($notifiable))
            ->line($trans->assertString('If you did not create an account, no further action is required.'));
    }

    /**
     * Get url.
     */
    protected function getUrl(mixed $notifiable): string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        Typer::assertTrue($this->token !== null && $this->email !== null && $this->locale !== null);

        $query = \http_build_query([
            'guard' => $this->guardName,
            'token' => $this->token,
            'email' => $this->email,
            'locale' => $this->locale,
        ]);

        return Resolver::resolveUrlGenerator()->to($this->spa ?? Trans::inject()->assertString('spa.email_verification_url')) . '?' . $query;
    }
}
