<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Ai\ConversationRepository;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Thinkycz\LaravelCore\Support\Config;
use Thinkycz\LaravelCore\Support\Resolver;

class ConversationController
{
    /**
     * Show the optional assistant workspace.
     */
    public function create(): Response
    {
        \abort_unless(Config::inject()->assertBool('ai.enabled'), 404);

        return Inertia::render('Assistant');
    }

    /**
     * Show a specific conversation.
     */
    public function show(string $id): RedirectResponse|Response
    {
        \abort_unless(Config::inject()->assertBool('ai.enabled'), 404);
        $user = User::mustAuth();
        $conversations = Resolver::resolve(ConversationRepository::class);
        $conversation = $conversations->findOwned($id, $user);

        if ($conversation === null) {
            return Resolver::resolveRedirector()->to('/assistant');
        }

        return Inertia::render('Assistant', [
            'conversation' => $conversations->dashboardPayload($conversation),
        ]);
    }

    /**
     * Delete an existing conversation.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        \abort_unless(Config::inject()->assertBool('ai.enabled'), 404);
        $user = User::mustAuth();
        $conversations = Resolver::resolve(ConversationRepository::class);
        $conversation = $conversations->findOwned($id, $user);

        if ($conversation === null) {
            return Resolver::resolveRedirector()->to('/assistant');
        }

        $conversations->delete($conversation);

        $referer = $request->header('referer');
        $isDeletingCurrent = false;
        if (\is_string($referer)) {
            $path = \parse_url($referer, \PHP_URL_PATH);
            if (\is_string($path) && $path === "/conversations/{$id}") {
                $isDeletingCurrent = true;
            }
        }

        if ($isDeletingCurrent) {
            return Resolver::resolveRedirector()->to('/assistant');
        }

        return Resolver::resolveRedirector()->back();
    }
}
