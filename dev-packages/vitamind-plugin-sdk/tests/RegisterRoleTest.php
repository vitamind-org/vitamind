<?php

namespace VitaminD\PluginSdk\Tests;

use VitaminD\PluginSdk\RegisterRole;
use VitaminD\PluginSdk\Tests\Fixtures\Plugins\PackageA\RoleRegistrar as PackageARegistrar;
use VitaminD\PluginSdk\Tests\Fixtures\Plugins\PackageB\RoleRegistrar as PackageBRegistrar;

class RegisterRoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RegisterRole::flush();
    }

    public function test_role_creation_and_fluent_methods(): void
    {
        $role = RegisterRole::make('admin-gudang')->title('Admin Gudang');

        $this->assertEquals('admin-gudang', $role->getShortKey());
        $this->assertEquals('Admin Gudang', $role->getTitle());
    }

    public function test_registering_a_role_prefixes_the_key_by_the_calling_package(): void
    {
        PackageARegistrar::registerRole('admin-gudang', 'Admin Gudang');

        $this->assertCount(1, RegisterRole::get());
        $this->assertNotNull(RegisterRole::find('package-a.admin-gudang'));
        $this->assertSame('package-a.admin-gudang', RegisterRole::find('package-a.admin-gudang')->getKey());
    }

    public function test_two_packages_register_roles_independently(): void
    {
        PackageARegistrar::registerRole('sales', 'Sales');
        PackageBRegistrar::registerRole('finance', 'Finance');

        $this->assertCount(2, RegisterRole::get());
        $this->assertNotNull(RegisterRole::find('package-a.sales'));
        $this->assertNotNull(RegisterRole::find('package-b.finance'));
    }

    public function test_two_packages_registering_the_same_short_key_do_not_collide(): void
    {
        PackageARegistrar::registerRole('owner', 'Package A Owner');
        PackageBRegistrar::registerRole('owner', 'Package B Owner');

        $this->assertCount(2, RegisterRole::get());

        $roleA = RegisterRole::find('package-a.owner');
        $roleB = RegisterRole::find('package-b.owner');

        $this->assertNotNull($roleA);
        $this->assertNotNull($roleB);
        $this->assertNotSame($roleA, $roleB);
        $this->assertSame('Package A Owner', $roleA->getTitle());
        $this->assertSame('Package B Owner', $roleB->getTitle());
    }

    public function test_find_returns_null_for_an_unregistered_key(): void
    {
        $this->assertNull(RegisterRole::find('nothing.here'));
    }

    public function test_flush_clears_the_registry(): void
    {
        PackageARegistrar::registerRole('sales', 'Sales');
        $this->assertCount(1, RegisterRole::get());

        RegisterRole::flush();

        $this->assertCount(0, RegisterRole::get());
    }

    public function test_key_is_null_before_registration(): void
    {
        $role = RegisterRole::make('sales')->title('Sales');

        $this->assertNull($role->getKey());
    }
}
