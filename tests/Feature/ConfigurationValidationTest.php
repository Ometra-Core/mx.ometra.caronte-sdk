<?php

namespace Tests\Feature;

use InvalidArgumentException;
use Ometra\Caronte\Providers\CaronteServiceProvider;
use Ometra\Caronte\Support\ConfiguredRoles;
use Tests\TestCase;

class ConfigurationValidationTest extends TestCase
{
    public function test_management_access_roles_must_exist_in_configured_roles(): void
    {
        config()->set('caronte.roles', [
            'root' => 'Default super administrator role',
        ]);
        config()->set('caronte.management.access_roles', ['operator']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown management access role [operator].');

        ConfiguredRoles::validate();
    }

    public function test_invalid_role_names_are_rejected(): void
    {
        config()->set('caronte.roles', [
            ['name' => 'Bad Role!', 'description' => 'Invalid'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('contains invalid characters');

        ConfiguredRoles::all();
    }

    public function test_role_names_with_dots_are_allowed(): void
    {
        config()->set('caronte.roles', [
            ['name' => 'Editor.Team', 'description' => 'Editor team role'],
        ]);

        $roles = ConfiguredRoles::keyedByName();

        $this->assertArrayHasKey('editor.team', $roles);
        $this->assertSame('Editor team role', $roles['editor.team']['description']);
    }

    public function test_root_role_is_always_present_after_normalization(): void
    {
        config()->set('caronte.roles', [
            'admin' => 'Administrative access',
        ]);

        $roles = ConfiguredRoles::keyedByName();

        $this->assertArrayHasKey('admin', $roles);
        $this->assertArrayHasKey('root', $roles);
        $this->assertSame('Default super administrator role', $roles['root']['description']);
    }

    public function test_caronte_url_must_use_https_unless_http_is_explicitly_allowed(): void
    {
        config()->set('caronte.url', 'http://caronte.test');
        config()->set('caronte.allow_http_requests', false);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CARONTE_URL must use HTTPS');

        $this->validatePackageConfig();
    }

    public function test_http_caronte_url_is_allowed_when_explicitly_enabled(): void
    {
        config()->set('caronte.url', 'http://caronte.test');
        config()->set('caronte.allow_http_requests', true);

        $this->validatePackageConfig();

        $this->assertTrue(true);
    }

    public function test_tenancy_mode_must_be_valid(): void
    {
        config()->set('caronte.tenancy.mode', 'shared');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caronte.tenancy.mode must be either multi or single');

        $this->validatePackageConfig();
    }

    public function test_legacy_auth_mode_is_rejected(): void
    {
        config()->set('caronte.auth_mode', 'legacy');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caronte.auth_mode must be jwt, oidc, or dual');

        $this->validatePackageConfig();
    }

    public function test_token_ttl_must_be_greater_than_zero(): void
    {
        config()->set('caronte.token.ttl_seconds', 0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caronte.token.ttl_seconds must be greater than zero');

        $this->validatePackageConfig();
    }

    public function test_token_timing_leeway_must_not_be_negative(): void
    {
        config()->set('caronte.token.clock_skew_seconds', -1);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caronte.token.clock_skew_seconds must be zero or greater');

        $this->validatePackageConfig();
    }

    public function test_single_tenant_mode_requires_configured_tenant_id(): void
    {
        config()->set('caronte.tenancy.mode', 'single');
        config()->set('caronte.tenancy.tenant_id', '');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('caronte.tenancy.tenant_id is required');

        $this->validatePackageConfig();
    }

    private function validatePackageConfig(): void
    {
        $provider = new CaronteServiceProvider($this->app);

        $validator = \Closure::bind(
            fn() => $this->validateCaronteConfig(),
            $provider,
            $provider::class
        );

        $validator();
    }
}
