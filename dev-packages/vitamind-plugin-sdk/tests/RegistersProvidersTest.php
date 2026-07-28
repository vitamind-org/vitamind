<?php

namespace VitaminD\PluginSdk\Tests;

use VitaminD\PluginSdk\Concerns\RegistersProviders;

class RegistersProvidersTest extends TestCase
{
    private object $mockRegistry;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockRegistry = new class {
            use RegistersProviders;
        };

        // Reset the static property of the anonymous class using Reflection
        $reflection = new \ReflectionClass($this->mockRegistry);
        $property = $reflection->getProperty('providers');
        $property->setValue([]);
    }

    public function test_registers_providers(): void
    {
        $registryClass = get_class($this->mockRegistry);

        $registryClass::register('github', 'GitHubProviderClass');
        $registryClass::register('gitlab', 'GitLabProviderClass');

        $this->assertEquals('GitHubProviderClass', $registryClass::get('github'));
        $this->assertEquals('GitLabProviderClass', $registryClass::get('gitlab'));
        $this->assertNull($registryClass::get('unknown'));

        $expectedAll = [
            'github' => 'GitHubProviderClass',
            'gitlab' => 'GitLabProviderClass',
        ];
        $this->assertEquals($expectedAll, $registryClass::all());
    }
}
