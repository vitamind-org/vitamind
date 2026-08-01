<?php

namespace VitaminD\PluginSdk\Tests;

use VitaminD\PluginSdk\RegisterCommand;

class RegisterCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear the config before each test
        config(['plugins.commands' => []]);
    }

    public function test_register_command(): void
    {
        $commandClass = 'App\\Console\\Commands\\MyPluginCommand';
        $register = RegisterCommand::make($commandClass);

        $register->register();

        $registered = RegisterCommand::get();
        $this->assertContains($commandClass, $registered);
        $this->assertEquals([$commandClass], config('plugins.commands'));
    }

    public function test_register_multiple_commands(): void
    {
        RegisterCommand::make('CommandA')->register();
        RegisterCommand::make('CommandB')->register();

        $registered = RegisterCommand::get();
        $this->assertEquals(['CommandA', 'CommandB'], $registered);
    }
}
