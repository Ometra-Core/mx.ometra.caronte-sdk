<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DelegatedAuthRoutesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('caronte.routes.auth_enabled', false);
        $app['config']->set('caronte.routes.login_url', 'https://identity.example.test/login');
    }

    public function test_login_routes_are_not_registered_when_auth_is_delegated(): void
    {
        $this->assertFalse(Route::has('caronte.login.form'));
        $this->assertFalse(Route::has('caronte.login'));
        $this->assertFalse(Route::has('caronte.oidc.login'));
        $this->assertFalse(Route::has('caronte.oidc.callback'));
        $this->assertFalse(Route::has('caronte.twoFactor.request'));
        $this->assertFalse(Route::has('caronte.password.recover.form'));
        $this->assertFalse(Route::has('caronte.api.auth.login'));
        $this->assertTrue(Route::has('caronte.api.auth.me'));
        $this->assertTrue(Route::has('caronte.api.auth.logout'));
        $this->assertTrue(Route::has('caronte.logout'));
        $this->assertTrue(Route::has('caronte.management.dashboard'));
    }

    public function test_protected_web_routes_redirect_to_the_delegated_login_application(): void
    {
        $this->get('/caronte/management')
            ->assertRedirectContains('https://identity.example.test/login?callback_url=');
    }
}
