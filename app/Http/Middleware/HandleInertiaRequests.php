<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Ai\ConversationRepository;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): string|null
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => [
                'name' => Config::inject()->assertString('app.name'),
                'locale' => Config::inject()->assertString('app.locale'),
                'locales' => Config::inject()->assertArray('app.locales'),
            ],
            'auth' => [
                'user' => fn(): array|null => $this->user(),
            ],
            'features' => ['ai' => Config::inject()->assertBool('ai.enabled'), 'ai_ready' => Config::inject()->parseNullableString('ai.providers.' . Config::inject()->assertString('ai.default') . '.key') !== null],
            'conversations' => fn(): array => Config::inject()->assertBool('ai.enabled') && $request->is('assistant', 'conversations/*') && $request->user() instanceof User
                ? Resolver::resolve(ConversationRepository::class)->recentForSidebar($request->user())
                : [],
            'flash' => [
                'success' => static fn(): string|null => self::flashMessage($request, 'success'),
                'error' => static fn(): string|null => self::flashMessage($request, 'error'),
            ],
        ];
    }

    /**
     * Resolve a flash message by key.
     *
     * Inertia v3 stores flash data under the dedicated `inertia.flash_data`
     * session key (see {@see Inertia::flash()}) and the Inertia middleware
     * reflashes the entry on every request. The Laravel session
     * `->flash('success', ...)` mechanism, by contrast, is consumed after a
     * single request and dies across an intermediate 302 redirect chain
     * (e.g. the authenticated visitor being bounced from /login to
     * /dashboard). We prefer the Inertia path so flashes survive the
     * 302 → guest-redirect → final render hop, and fall back to the
     * plain session key for same-request controllers that still use
     * `$request->session()->flash(...)`.
     */
    protected static function flashMessage(Request $request, string $key): string|null
    {
        $inertiaFlash = Inertia::getFlashed($request);

        if (isset($inertiaFlash[$key]) && \is_string($inertiaFlash[$key])) {
            return $inertiaFlash[$key];
        }

        return Typer::assertNullableString($request->session()->get($key));
    }

    /**
     * Resolve the authenticated user for shared Inertia props.
     *
     * @return array<string, mixed>|null
     */
    protected function user(): array|null
    {
        $user = Resolver::resolveAuthManager()->guard('users')->user();

        if ($user instanceof User === false) {
            return null;
        }

        return [
            'id' => $user->getKey(),
            'email' => $user->getEmail(),
            'locale' => $user->getLocale(),
            'email_verified_at' => $user->getEmailVerifiedAt()?->toJSON(),
        ];
    }
}
