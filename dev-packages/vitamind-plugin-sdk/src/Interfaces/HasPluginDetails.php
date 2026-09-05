<?php

namespace VitaminD\PluginSdk\Interfaces;

interface HasPluginDetails
{
    /**
     * @return array{key: string, name: string, description: string}
     */
    public function pluginDetails(): array;
}
