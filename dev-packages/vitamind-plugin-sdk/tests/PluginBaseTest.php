<?php

namespace VitaminD\PluginSdk\Tests;

use LogicException;
use VitaminD\PluginSdk\PluginBase;
use VitaminD\PluginSdk\RegisterRole;

class PluginBaseTest extends TestCase
{
    public function test_plugin_details_are_returned_correctly(): void
    {
        $provider = new class($this->app) extends PluginBase
        {
            public function pluginDetails(): array
            {
                return [
                    'key' => 'test-service-provider',
                    'name' => 'Test Service Provider',
                    'description' => 'A ServiceProvider-based plugin for testing purposes.',
                ];
            }
        };

        $this->assertSame([
            'key' => 'test-service-provider',
            'name' => 'Test Service Provider',
            'description' => 'A ServiceProvider-based plugin for testing purposes.',
        ], $provider->pluginDetails());
    }

    public function test_register_role_scopes_the_role_to_the_plugins_own_key(): void
    {
        RegisterRole::flush();

        $provider = new class($this->app) extends PluginBase
        {
            public function pluginDetails(): array
            {
                return ['key' => 'acme-provider', 'name' => 'Acme Provider', 'description' => ''];
            }

            public function callRegisterRole(): void
            {
                $this->registerRole('manager', 'Manager');
            }
        };

        $provider->callRegisterRole();

        $this->assertNotNull(RegisterRole::find('acme-provider.manager'));
    }

    public function test_plugin_key_throws_when_plugin_details_omits_key(): void
    {
        $provider = new class($this->app) extends PluginBase
        {
            public function pluginDetails(): array
            {
                return ['name' => 'No Key', 'description' => ''];
            }

            public function callRegisterRole(): void
            {
                $this->registerRole('manager', 'Manager');
            }
        };

        $this->expectException(LogicException::class);

        $provider->callRegisterRole();
    }
}
