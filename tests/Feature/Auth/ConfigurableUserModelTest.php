<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use Basics13\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

final class ConfigurableUserModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authentication_uses_the_model_configured_by_the_host(): void
    {
        config(['auth.providers.users.model' => ConfiguredAuthUser::class]);
        Auth::forgetGuards();

        $user = ConfiguredAuthUser::query()->create([
            'name' => 'Configured User',
            'email' => 'configured@example.com',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_creates_the_model_configured_by_the_host(): void
    {
        config(['auth.providers.users.model' => ConfiguredAuthUser::class]);
        Auth::forgetGuards();

        $this->post(route('register.store'), [
            'name' => 'Configured User',
            'email' => 'configured@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertInstanceOf(ConfiguredAuthUser::class, Auth::user());
        $this->assertDatabaseHas('users', ['email' => 'configured@example.com']);
    }
}

final class ConfiguredAuthUser extends User
{
    protected $table = 'users';
}
