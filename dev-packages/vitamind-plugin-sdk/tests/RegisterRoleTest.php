<?php

namespace VitaminD\PluginSdk\Tests;

use VitaminD\PluginSdk\RegisterRole;

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

    public function test_registering_a_role_stores_it_under_the_explicit_plugin_key(): void
    {
        RegisterRole::make('admin-gudang')->title('Admin Gudang')->register('acme');

        $this->assertCount(1, RegisterRole::get());
        $this->assertNotNull(RegisterRole::find('acme.admin-gudang'));
        $this->assertSame('acme.admin-gudang', RegisterRole::find('acme.admin-gudang')->getKey());
    }

    public function test_two_different_plugin_keys_do_not_collide(): void
    {
        RegisterRole::make('sales')->title('Sales')->register('acme');
        RegisterRole::make('finance')->title('Finance')->register('other');

        $this->assertCount(2, RegisterRole::get());
        $this->assertNotNull(RegisterRole::find('acme.sales'));
        $this->assertNotNull(RegisterRole::find('other.finance'));
    }

    public function test_the_same_short_key_under_two_different_plugin_keys_does_not_collide(): void
    {
        RegisterRole::make('owner')->title('Acme Owner')->register('acme');
        RegisterRole::make('owner')->title('Other Owner')->register('other');

        $this->assertCount(2, RegisterRole::get());

        $roleA = RegisterRole::find('acme.owner');
        $roleB = RegisterRole::find('other.owner');

        $this->assertNotNull($roleA);
        $this->assertNotNull($roleB);
        $this->assertNotSame($roleA, $roleB);
        $this->assertSame('Acme Owner', $roleA->getTitle());
        $this->assertSame('Other Owner', $roleB->getTitle());
    }

    public function test_find_returns_null_for_an_unregistered_key(): void
    {
        $this->assertNull(RegisterRole::find('nothing.here'));
    }

    public function test_flush_clears_the_registry(): void
    {
        RegisterRole::make('sales')->title('Sales')->register('acme');
        $this->assertCount(1, RegisterRole::get());

        RegisterRole::flush();

        $this->assertCount(0, RegisterRole::get());
    }

    public function test_key_is_null_before_registration(): void
    {
        $role = RegisterRole::make('sales')->title('Sales');

        $this->assertNull($role->getKey());
    }

    public function test_registering_without_a_plugin_key_fails(): void
    {
        $this->expectException(\ArgumentCountError::class);

        // @phpstan-ignore-next-line arguments.count (deliberately testing the missing-argument failure)
        RegisterRole::make('sales')->title('Sales')->register();
    }

    public function test_key_for_computes_the_join_format_without_registering(): void
    {
        $this->assertSame('acme.admin-gudang', RegisterRole::keyFor('acme', 'admin-gudang'));
        $this->assertCount(0, RegisterRole::get());
    }
}
