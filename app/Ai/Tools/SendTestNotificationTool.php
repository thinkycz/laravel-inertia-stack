<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\User;
use App\Notifications\AgentTestNotification;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SendTestNotificationTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): string
    {
        return 'Send a test email notification to the authenticated user. The user must approve the subject and message before it is sent.';
    }

    /**
     * Get the tool schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'subject' => $schema->string()
                ->description('The concise subject of the test notification.')
                ->required(),
            'message' => $schema->string()
                ->description('The body of the test notification.')
                ->required(),
        ];
    }

    /**
     * Queue the approved notification for the authenticated user.
     */
    public function handle(Request $request): string
    {
        $subject = $this->requiredString($request['subject'] ?? null, 'subject', 255);
        $message = $this->requiredString($request['message'] ?? null, 'message', 5000);
        $user = User::mustAuth();

        $user->notify((new AgentTestNotification($subject, $message))->locale($user->getLocale()));

        return 'The test notification was queued.';
    }

    /**
     * Normalize a required string argument.
     */
    private function requiredString(mixed $value, string $name, int $maxLength): string
    {
        if (!\is_string($value) || \mb_trim($value) === '') {
            throw new InvalidArgumentException("The {$name} argument must be a non-empty string.");
        }

        $value = \mb_trim($value);

        if ($maxLength < \mb_strlen($value)) {
            throw new InvalidArgumentException("The {$name} argument is too long.");
        }

        return $value;
    }
}
