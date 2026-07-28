<?php

namespace VitaminD\PluginSdk;

use VitaminD\PluginSdk\Interfaces\PluginInterface;

abstract class AbstractPlugin implements PluginInterface
{
    protected string $name = '';

    protected string $description = '';

    protected array $dependencies = [];

    public function boot(): void {}

    public function enable(): void {}

    public function disable(): void {}

    public function install(): void {}

    public function uninstall(): void {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDependencies(): array
    {
        return $this->dependencies;
    }
}
