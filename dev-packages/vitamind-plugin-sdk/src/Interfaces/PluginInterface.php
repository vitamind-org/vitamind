<?php

namespace VitaminD\PluginSdk\Interfaces;

interface PluginInterface extends HasPluginDetails
{
    public function boot(): void;

    public function enable(): void;

    public function disable(): void;

    public function install(): void;

    public function uninstall(): void;

    public function getDependencies(): array;
}
