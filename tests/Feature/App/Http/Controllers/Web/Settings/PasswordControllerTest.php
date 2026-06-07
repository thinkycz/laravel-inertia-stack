<?php

declare(strict_types=1);

namespace Tests\Feature\App\Http\Controllers\Web\Settings;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Thinkycz\LaravelCore\Support\Resolver;
use Thinkycz\LaravelCore\Support\Typer;

class PasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_password_settings(): void
    {
        $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

        $response = $this->be($user, 'users')->get('/settings/password', $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'settings/Password');
    }

    public function test_authenticated_user_can_update_password(): void
    {
        $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

        $response = $this->be($user, 'users')->post('/settings/password', [
            'password' => UserFactory::$password,
            'new_password' => 'new-password',
        ], $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'settings/Password');

        $user->refresh();

        static::assertTrue(Resolver::resolveHasher()->check('new-password', $user->getAuthPassword()));
    }

    public function test_password_update_revokes_existing_database_tokens(): void
    {
        $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

        Resolver::resolveDatabaseTokenGuard($user->getTable())->login($user);

        $this->assertDatabaseCount('database_tokens', 1);

        $this->be($user, 'users')->post('/settings/password', [
            'password' => UserFactory::$password,
            'new_password' => 'new-password',
        ], $this->inertiaHeaders());

        $this->assertDatabaseCount('database_tokens', 0);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

        $response = $this->be($user, 'users')->post('/settings/password', [
            'password' => 'wrong-password',
            'new_password' => 'new-password',
        ]);

        $response->assertStatus(422);
    }
}
