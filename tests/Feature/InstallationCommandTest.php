<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InstallationCommandTest extends TestCase
{
    public function test_install_command_publishes_required_ui_assets(): void
    {
        $this->artisan('caronte:install', ['--force' => true])
            ->expectsOutputToContain('Caronte is installed.')
            ->assertExitCode(0);

        $this->assertFileExists(public_path('vendor/caronte/css/custom.css'));
        $this->assertFileExists(public_path('vendor/caronte/brand/bg.png'));
        $this->assertFileExists(public_path('vendor/caronte/brand/logo_caronte.png'));
        $this->assertFileExists(public_path('vendor/caronte/brand/ometra-logo.png'));
        $this->assertFileDoesNotExist(public_path('vendor/caronte/caronte.css'));
    }

    public function test_ui_update_command_refreshes_assets_and_inertia_components(): void
    {
        $this->artisan('caronte:ui:update')
            ->expectsOutputToContain('Caronte UI assets and components were updated.')
            ->assertExitCode(0);

        $this->assertFileExists(public_path('vendor/caronte/css/custom.css'));
        $this->assertFileExists(resource_path('js/vendor/caronte/Pages/auth/login.tsx'));
    }

    public function test_installation_commands_are_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('caronte:install', $commands);
        $this->assertArrayHasKey('caronte:ui:update', $commands);
    }

    public function test_default_branding_uses_published_package_assets(): void
    {
        $branding = config('caronte.ui.branding');

        $this->assertSame('/vendor/caronte/brand/logo_caronte.png', $branding['logo_url']);
        $this->assertSame('/vendor/caronte/brand/bg.png', $branding['background_url']);
        $this->assertSame('/vendor/caronte/brand/ometra-logo.png', $branding['footer_logo_url']);
    }
}
