<?php

namespace VitaminD\Core\Actions\Plugins;

use VitaminD\Core\Actions\Bootstrap\GetBootstrap;
use VitaminD\Core\Events\PluginStateChanged;
use VitaminD\Core\Models\Plugin;
use VitaminD\Core\Models\PluginError;
use Exception;
use Throwable;

final readonly class EnablePlugin
{
    public function __construct(
        private GetPluginInstance $getImplementation,
        private PluginCache $cache,
    ) {}

    /**
     * @throws Exception
     */
    public function handle(Plugin $plugin): void
    {
        if ($plugin->is_enabled) {
            throw new Exception('This plugin is already enabled');
        }

        $implementation = $this->getImplementation->handle($plugin);
        if ($implementation === null) {
            throw new Exception('Unable to enable the plugin, please check the error logs');
        }

        foreach ($implementation->getDependencies() as $dependencyNamespace) {
            $isActive = Plugin::where('namespace', $dependencyNamespace)
                ->where('is_enabled', true)
                ->exists();

            if (! $isActive) {
                $depPlugin = Plugin::where('namespace', $dependencyNamespace)->first();
                $depName = $depPlugin ? $depPlugin->name : $dependencyNamespace;
                throw new Exception("Cannot enable '{$plugin->name}'. Required dependency '{$depName}' is not installed or enabled.");
            }
        }

        try {
            $plugin->name = $implementation->getName();
            $plugin->description = $implementation->getDescription();
            $implementation->enable();
        } catch (Throwable $ex) {
            PluginError::createFromException($ex, $plugin);
            throw new Exception('Unable to enable the plugin, please check the error logs');
        }

        $plugin->is_enabled = true;
        $plugin->save();

        $this->cache->clear();

        GetBootstrap::forgetVersion();

        PluginStateChanged::dispatch($plugin, 'enabled');
    }
}
