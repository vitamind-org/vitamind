<?php

namespace VitaminD\Core\Actions\Plugins;

use VitaminD\Core\Actions\Bootstrap\GetBootstrap;
use VitaminD\Core\Events\PluginStateChanged;
use VitaminD\Core\Models\Plugin;
use VitaminD\Core\Models\PluginError;
use Exception;
use Throwable;

final readonly class InstallPlugin
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
        $implementation = $this->getImplementation->handle($plugin);
        if ($implementation === null) {
            throw new Exception('Unable to install the plugin, please check the error logs');
        }

        $plugin->name = $implementation->getName();
        $plugin->description = $implementation->getDescription();
        $plugin->save();

        try {
            $implementation->install();
        } catch (Throwable $ex) {
            PluginError::createFromException($ex, $plugin);
            throw new Exception('Unable to install the plugin, please check the error logs');
        }

        $plugin->is_installed = true;
        $plugin->save();

        $this->cache->clear();

        GetBootstrap::forgetVersion();

        PluginStateChanged::dispatch($plugin, 'installed');
    }
}
