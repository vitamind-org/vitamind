<?php

namespace VitaminD\PluginSdk\Tests;

use LogicException;
use VitaminD\PluginSdk\AbstractPlugin;
use VitaminD\PluginSdk\RegisterRole;

class AbstractPluginTest extends TestCase
{
    public function test_plugin_details_are_returned_correctly(): void
    {
        $plugin = new class extends AbstractPlugin
        {
            public function pluginDetails(): array
            {
                return [
                    'key' => 'test-plugin',
                    'name' => 'Test Plugin',
                    'description' => 'A plugin for testing purposes.',
                ];
            }
        };

        $this->assertSame([
            'key' => 'test-plugin',
            'name' => 'Test Plugin',
            'description' => 'A plugin for testing purposes.',
        ], $plugin->pluginDetails());
    }

    public function test_plugin_lifecycle_methods_can_be_called_without_error(): void
    {
        $plugin = new class extends AbstractPlugin
        {
            public function pluginDetails(): array
            {
                return ['key' => 'lifecycle-test', 'name' => 'Lifecycle Test', 'description' => ''];
            }
        };

        // Verify lifecycle methods exist and are callable
        $plugin->boot();
        $plugin->enable();
        $plugin->disable();
        $plugin->install();
        $plugin->uninstall();

        $this->assertTrue(true); // Asserting that execution completed without throwing exceptions
    }

    public function test_plugin_dependencies_can_be_defined_and_retrieved(): void
    {
        $plugin = new class extends AbstractPlugin
        {
            protected array $dependencies = [
                'App\Plugins\Local\Acme\HelloWorld\Plugin',
            ];

            public function pluginDetails(): array
            {
                return ['key' => 'deps-test', 'name' => 'Deps Test', 'description' => ''];
            }
        };

        $this->assertEquals([
            'App\Plugins\Local\Acme\HelloWorld\Plugin',
        ], $plugin->getDependencies());
    }

    public function test_register_role_scopes_the_role_to_the_plugins_own_key(): void
    {
        RegisterRole::flush();

        $plugin = new class extends AbstractPlugin
        {
            public function pluginDetails(): array
            {
                return ['key' => 'acme', 'name' => 'Acme', 'description' => ''];
            }

            public function callRegisterRole(): void
            {
                $this->registerRole('manager', 'Manager');
            }
        };

        $plugin->callRegisterRole();

        $this->assertNotNull(RegisterRole::find('acme.manager'));
    }

    public function test_plugin_key_throws_when_plugin_details_omits_key(): void
    {
        $plugin = new class extends AbstractPlugin
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

        $plugin->callRegisterRole();
    }
}
