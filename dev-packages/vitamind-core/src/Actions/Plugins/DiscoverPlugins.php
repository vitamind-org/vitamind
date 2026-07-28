<?php

namespace VitaminD\Core\Actions\Plugins;

use VitaminD\Core\Enums\PluginSource;
use VitaminD\Core\Models\Plugin;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

final readonly class DiscoverPlugins
{
    public function __construct(
        private PluginCache $cache,
    ) {}

    public function handle(): void
    {
        // Guards against schema drift during `artisan migrate` itself: every
        // console command boots the app (running this via the `booted()`
        // hook) before its own logic runs, including migrate — so a pending
        // column addition must not crash discovery before migrate can apply it.
        if (! Schema::hasTable('plugins') || ! Schema::hasColumns('plugins', ['source', 'installed_at'])) {
            return;
        }

        $discovered = [
            ...$this->discoverLocalPlugins(),
            ...$this->discoverPackagedPlugins(plugins_path(), PluginSource::GITHUB),
            ...$this->discoverPackagedPlugins(base_path('vendor'.DIRECTORY_SEPARATOR.'vitamind'), PluginSource::COMPOSER),
        ];

        $plugins = Plugin::all();

        foreach ($discovered as $entry) {
            $exists = $plugins->contains(fn (Plugin $plugin) => $plugin->source === $entry['source'] && $plugin->folder === $entry['folder']);

            if (! $exists) {
                Plugin::create([
                    'folder' => $entry['folder'],
                    'namespace' => $entry['namespace'],
                    'source' => $entry['source'],
                    // Local plugins are application code, already "installed"; packaged
                    // plugins (GitHub/Composer) are merely discovered until an admin installs them.
                    'is_installed' => $entry['source'] === PluginSource::LOCAL,
                    'is_enabled' => $entry['source'] === PluginSource::LOCAL,
                    'installed_at' => $entry['source'] === PluginSource::LOCAL ? now() : null,
                ]);
            }
        }

        $discoveredKeys = collect($discovered)
            ->map(fn (array $entry) => $entry['source']->value.'|'.$entry['folder'])
            ->all();

        $plugins->each(function (Plugin $plugin) use ($discoveredKeys) {
            if (! in_array($plugin->source->value.'|'.$plugin->folder, $discoveredKeys)) {
                $plugin->delete();
            }
        });

        $this->cache->clear();
    }

    /**
     * @return array<int, array{folder: string, namespace: string, source: PluginSource}>
     */
    private function discoverLocalPlugins(): array
    {
        $pluginsPath = app_path('Plugins');

        if (! File::exists($pluginsPath)) {
            File::makeDirectory($pluginsPath, 0755, true);
        }

        $globPath = implode(DIRECTORY_SEPARATOR, [$pluginsPath, '*']);

        return collect(File::glob($globPath))
            ->filter(fn ($path) => File::isDirectory($path) && File::exists($path.DIRECTORY_SEPARATOR.'Plugin.php'))
            ->map(function ($path) use ($pluginsPath) {
                $folder = substr($path, strlen($pluginsPath) + 1);

                return [
                    'folder' => $folder,
                    'namespace' => 'App\\Plugins\\'.$folder.'\\Plugin',
                    'source' => PluginSource::LOCAL,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Discover plugins packaged with their own composer.json (GitHub clones
     * in storage/plugins, or Composer packages in vendor/vitamind). Their
     * namespace is resolved from the PSR-4 autoload map rather than guessed,
     * and — for GitHub plugins, which aren't part of the Composer autoloader —
     * registered for autoloading at runtime.
     *
     * @return array<int, array{folder: string, namespace: string, source: PluginSource}>
     */
    private function discoverPackagedPlugins(string $basePath, PluginSource $source): array
    {
        if (! File::isDirectory($basePath)) {
            return [];
        }

        return collect(File::directories($basePath))
            ->map(function (string $path) use ($source) {
                $folder = basename($path);
                $meta = $this->cache->resolvePluginMetadata($source->value, $folder, $path);

                if (! $meta) {
                    return null;
                }

                if ($source === PluginSource::GITHUB) {
                    $this->registerAutoload($meta['prefix'], $path.DIRECTORY_SEPARATOR.$meta['srcPath']);
                }

                return [
                    'folder' => $folder,
                    'namespace' => $meta['namespace'],
                    'source' => $source,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function registerAutoload(string $prefix, string $srcPath): void
    {
        /** @var \Composer\Autoload\ClassLoader $loader */
        $loader = require base_path('vendor'.DIRECTORY_SEPARATOR.'autoload.php');
        $loader->addPsr4($prefix, $srcPath);
    }
}
