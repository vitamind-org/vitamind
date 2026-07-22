<?php

namespace VitaminD\Core\Actions\Plugins;

use VitaminD\Core\Actions\Bootstrap\GetBootstrap;
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

        $folder = $this->path_join([app_path('Plugins/Local'), $plugin->folder]);
        File::deleteDirectory($folder);

        $subFolder = dirname($folder);
        if (count(File::directories($subFolder)) === 0) {
            File::deleteDirectory($subFolder);
        }

        $plugin->delete();

        $this->cache->clear();

        GetBootstrap::forgetVersion();

        PluginStateChanged::dispatch($plugin, 'uninstalled');
    }

    public function path_join(array $strings): string
    {
        return implode(DIRECTORY_SEPARATOR, $strings);
    }
}
