<?php

declare(strict_types=1);

namespace Tests\Feature\App\Http\Controllers\Web\Settings;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Thinkycz\LaravelCore\Support\Typer;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_profile_settings(): void
    {
        $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

        $response = $this->be($user, 'users')->get('/settings/profile', $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'settings/Profile');
    }

    public function test_authenticated_user_can_update_profile_settings(): void
    {
        $user = Typer::assertInstance(UserFactory::new()->createOne(), User::class);

        $response = $this->be($user, 'users')->post('/settings/profile', [
            'email' => 'updated@example.com',
            'locale' => 'cs',
        ], $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'settings/Profile');
        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'email' => 'updated@example.com',
            'locale' => 'cs',
        ]);
    }
}
