<?php

namespace VitaminD\PluginSdk;

use VitaminD\PluginSdk\Concerns\RegistersOwnRole;
use VitaminD\PluginSdk\Interfaces\PluginInterface;

abstract class AbstractPlugin implements PluginInterface
{
    use RegistersOwnRole;

    protected array $dependencies = [];

    /**
     * @return array{key: string, name: string, description: string}
     */
    abstract public function pluginDetails(): array;

    public function boot(): void {}

    public function enable(): void {}

    public function disable(): void {}

    public function install(): void {}

    public function uninstall(): void {}

    public function getDependencies(): array
    {
        return $this->dependencies;
    }
}
