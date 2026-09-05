<?php

namespace VitaminD\PluginSdk;

use Illuminate\Support\ServiceProvider;
use VitaminD\PluginSdk\Concerns\RegistersOwnRole;
use VitaminD\PluginSdk\Interfaces\HasPluginDetails;

/**
 * Base `ServiceProvider` every first-party (Composer-package) VitaminD
 * plugin — `vitamind-workspace-plugin`, `vitamind-archive-plugin`,
 * `vitamind-realtime-plugin`, and any future one — extends instead of
 * Laravel's own `ServiceProvider`. Forces an explicit, self-declared
 * identity (`pluginDetails()['key']`) rather than guessing it from the
 * class's own namespace, mirroring `AbstractPlugin` for the
 * dynamically-installed (local/GitHub/Composer) plugin system.
 */
abstract class PluginBase extends ServiceProvider implements HasPluginDetails
{
    use RegistersOwnRole;

    /**
     * @return array{key: string, name: string, description: string}
     */
    abstract public function pluginDetails(): array;
}
