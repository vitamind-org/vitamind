<?php

namespace VitaminD\PluginSdk\Tests\Fixtures\Plugins\PackageA;

use VitaminD\PluginSdk\RegisterRole;

class RoleRegistrar
{
    public static function registerRole(string $key, string $title): void
    {
        RegisterRole::make($key)->title($title)->register();
    }
}
