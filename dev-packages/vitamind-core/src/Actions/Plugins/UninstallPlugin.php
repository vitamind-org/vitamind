<?php

namespace VitaminD\Core\Actions\Plugins;

use VitaminD\Core\Actions\Bootstrap\GetBootstrap;
use VitaminD\Core\Enums\PluginSource;
use VitaminD\Core\Events\PluginStateChanged;
use VitaminD\Core\Models\Plugin;
use VitaminD\Core\Models\PluginError;
use Exception;
use Illuminate\Support\Facades\File;
use Throwable;

final readonly class UninstallPlugin
{
    public function __construct(
        private GetPluginInstance $getImplementation,
        private PluginCache $cache,
    ) {}

    /**
     * @throws Exception
     */
    public function handle(Plugin $plugin, bool $force = false): void
    {
        if ($plugin->is_enabled) {
            throw new Exception('Unable to uninstall an enabled plugin, disable the plugin first');
        }

        if ($plugin->source === PluginSource::COMPOSER) {
            throw new Exception("This plugin was installed via Composer. Run 'composer remove' to uninstall it.");
        }

        if ($plugin->is_installed) {
            $implementation = $this->getImplementation->handle($plugin);
            if ($implementation === null) {
                throw new Exception('Unable to uninstall the plugin, please check the error logs');
            }

            try {
                $implementation->uninstall();
            } catch (Throwable $ex) {
                if (! $force) {
                    PluginError::createFromException($ex, $plugin);
                    throw new Exception('Unable to uninstall the plugin, please check the error logs');
                }
            }
        }

        $basePath = $plugin->source === PluginSource::GITHUB ? plugins_path() : app_path('Plugins');
        File::deleteDirectory($basePath.DIRECTORY_SEPARATOR.$plugin->folder);

        $plugin->delete();

        $this->cache->clear();

        GetBootstrap::forgetVersion();

        PluginStateChanged::dispatch($plugin, 'uninstalled');
    }
}
