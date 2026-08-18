<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TenantSwitcherTest extends TestCase
{
    public function test_blade_switcher_is_hidden_with_fewer_than_two_tenants(): void
    {
        $empty = Blade::render('<x-caronte::tenant-switcher :tenants="[]" />');
        $single = Blade::render(
            '<x-caronte::tenant-switcher :tenants="$tenants" />',
            ['tenants' => [['id_tenant' => 'tenant-a', 'name' => 'Tenant A']]]
        );

        $this->assertStringNotContainsString('caronte-tenant-switcher', $empty);
        $this->assertStringNotContainsString('caronte-tenant-switcher', $single);
    }

    public function test_blade_switcher_renders_all_tenants_and_marks_the_current_one(): void
    {
        $html = Blade::render(
            '<x-caronte::tenant-switcher :tenants="$tenants" current-tenant-id="tenant-b" />',
            ['tenants' => [
                ['id_tenant' => 'tenant-a', 'name' => 'Tenant A'],
                ['id_tenant' => 'tenant-b', 'name' => 'Tenant B'],
            ]]
        );

        $this->assertStringContainsString('Tenant A', $html);
        $this->assertStringContainsString('Tenant B', $html);
        $this->assertMatchesRegularExpression('/value="tenant-b"\s+selected/', $html);
    }
}
