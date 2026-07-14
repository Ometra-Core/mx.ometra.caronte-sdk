<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteRegistrationTest extends TestCase
{
    public function test_auth_and_management_routes_are_registered_when_management_is_enabled(): void
    {
        $this->assertTrue(Route::has('caronte.login.form'));
        $this->assertTrue(Route::has('caronte.login'));
        $this->assertTrue(Route::has('caronte.api.auth.login'));
        $this->assertTrue(Route::has('caronte.api.auth.me'));
        $this->assertTrue(Route::has('caronte.api.auth.logout'));
        $this->assertFalse(Route::has('caronte.api.auth.exchange'));
        $this->assertTrue(Route::has('caronte.management.dashboard'));
        $this->assertTrue(Route::has('caronte.management.roles.sync'));
        $this->assertFalse(Route::has('caronte.management.roles.create'));
        $this->assertFalse(Route::has('caronte.management.roles.update'));
        $this->assertFalse(Route::has('caronte.management.roles.delete'));
        $this->assertTrue(Route::has('caronte.management.users.list'));
        $this->assertTrue(Route::has('caronte.management.users.show'));
        $this->assertFalse(Route::has('caronte.management.users.update'));
        $this->assertTrue(Route::has('caronte.management.users.update.direct'));
        $this->assertFalse(Route::has('caronte.management.users.delete'));
        $this->assertTrue(Route::has('caronte.management.users.delete.direct'));
        $this->assertTrue(Route::has('caronte.management.users.roles.list'));
        $this->assertTrue(Route::has('caronte.management.suite.users.applications.roles.sync'));
    }
}
