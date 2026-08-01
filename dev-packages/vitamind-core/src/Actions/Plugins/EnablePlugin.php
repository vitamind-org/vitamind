<?php

namespace VitaminD\Core\Actions\Plugins;

use VitaminD\Core\Actions\Bootstrap\GetBootstrap;
use VitaminD\Core\Enums\PluginSource;
use VitaminD\Core\Events\PluginStateChanged;
use VitaminD\Core\Models\Plugin;
use VitaminD\Core\Models\PluginError;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
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
            $this->runPendingMigrations($plugin);

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

    /**
     * Migration paths are registered with the migrator on every boot (see
     * DiscoverPlugins), but registering a path never runs anything — only
     * `artisan migrate` does. Enable is the deliberate "activate this now"
     * moment, so it's the right place to apply the plugin's own pending
     * migrations rather than leaving admins to discover a missing-table
     * error and run migrate by hand.
     */
    private function runPendingMigrations(Plugin $plugin): void
    {
        $basePath = match ($plugin->source) {
            PluginSource::LOCAL => app_path('Plugins'),
            PluginSource::GITHUB => plugins_path(),
            PluginSource::COMPOSER => base_path('vendor'.DIRECTORY_SEPARATOR.'vitamind'),
        };

        $migrationsPath = implode(DIRECTORY_SEPARATOR, [$basePath, $plugin->folder, 'database', 'migrations']);

        if (! File::isDirectory($migrationsPath)) {
            return;
        }

        Artisan::call('migrate', [
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--force' => true,
        ]);
    }
}
