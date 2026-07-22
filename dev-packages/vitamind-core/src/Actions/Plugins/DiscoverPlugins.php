<?php

namespace VitaminD\Core\Actions\Plugins;

use VitaminD\Core\Models\Plugin;
use Illuminate\Support\Facades\File;

final readonly class DiscoverPlugins
{
    public function __construct(
        private PluginCache $cache,
    ) {}

    public function handle(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('plugins')) {
            return;
        }

        $pluginsPath = app_path('Plugins'.DIRECTORY_SEPARATOR.'Local');
        
        // Ensure local plugins path exists
        if (! File::exists($pluginsPath)) {
            File::makeDirectory($pluginsPath, 0755, true);
        }

        $globPath = implode(DIRECTORY_SEPARATOR, [$pluginsPath, '*', '*']);
        $pluginFolders = collect(File::glob($globPath))
            ->filter(fn ($path) => File::isDirectory($path))
            ->map(fn ($path) => substr($path, strlen($pluginsPath) + 1))
            ->toArray();

        $plugins = Plugin::all();

        foreach ($pluginFolders as $folder) {
            if (! $plugins->contains('folder', $folder)) {
                $namespace = str_replace(DIRECTORY_SEPARATOR, '\\', $folder);
                Plugin::create([
                    'folder' => $folder,
                    'namespace' => 'VitaminD\Core\\Plugins\\Local\\'.$namespace.'\\Plugin',
                    'is_installed' => true,
                    'is_enabled' => true, // Auto enable for local plugins in core
                ]);
            }
        }

        $plugins->each(function ($plugin) use ($pluginFolders) {
            if (! in_array($plugin->folder, $pluginFolders)) {
                $plugin->delete();
            }
        });

        $this->cache->clear();
    }
}
