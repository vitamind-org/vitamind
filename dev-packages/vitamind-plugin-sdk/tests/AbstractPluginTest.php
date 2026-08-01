<?php

namespace VitaminD\PluginSdk\Tests;

use VitaminD\PluginSdk\AbstractPlugin;

class AbstractPluginTest extends TestCase
{
    public function test_plugin_getters_return_correct_properties(): void
    {
        $plugin = new class extends AbstractPlugin {
            protected string $name = 'Test Plugin';
            protected string $description = 'A plugin for testing purposes.';
        };

        $this->assertEquals('Test Plugin', $plugin->getName());
        $this->assertEquals('A plugin for testing purposes.', $plugin->getDescription());
    }

    public function test_plugin_lifecycle_methods_can_be_called_without_error(): void
    {
        $plugin = new class extends AbstractPlugin {};

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
        $plugin = new class extends AbstractPlugin {
            protected array $dependencies = [
                'App\Plugins\Local\Acme\HelloWorld\Plugin',
            ];
        };

        $this->assertEquals([
            'App\Plugins\Local\Acme\HelloWorld\Plugin',
        ], $plugin->getDependencies());
    }
}
