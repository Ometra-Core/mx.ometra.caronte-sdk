<?php

namespace Tests\Feature;

use Illuminate\Contracts\Foundation\Application;
use Inertia\Inertia;
use Ometra\Caronte\Providers\CaronteServiceProvider;
use Tests\TestCase;

class FrameworkCompatibilityContractTest extends TestCase
{
    public function test_composer_contract_supports_both_framework_generations(): void
    {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('^12.0 || ^13.0', $composer['require']['laravel/framework']);
        $this->assertSame('^12.0 || ^13.0', $composer['require']['illuminate/support']);
        $this->assertSame('^3.1', $composer['require']['equidna/bee-hive']);
        $this->assertSame('^2.0', $composer['require']['inertiajs/inertia-laravel']);
        $this->assertSame('^10.0 || ^11.0', $composer['require-dev']['orchestra/testbench']);
    }

    public function test_service_provider_is_registered_and_boots_with_inertia(): void
    {
        $this->assertInstanceOf(
            CaronteServiceProvider::class,
            $this->app->getProvider(CaronteServiceProvider::class),
        );
        $this->assertInstanceOf(Application::class, $this->app);

        $shared = Inertia::getShared('caronte');

        $this->assertIsCallable($shared);
        $this->assertArrayHasKey('branding', $shared());
    }
}
