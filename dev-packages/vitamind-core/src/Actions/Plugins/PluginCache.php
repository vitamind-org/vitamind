<?php

namespace VitaminD\Core\Actions\Plugins;

use VitaminD\Core\Models\Plugin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Throwable;

final class PluginCache
{
    private const string CACHE_KEY = 'active-plugins';

    private const int METADATA_TTL_DAYS = 30;

    public function __construct() {}

    /**
     * Retrieves active plugins
     *
     * @return Collection<int, Plugin>
     */
    public function get(): Collection
    {
        try {
            $ids = Cache::get(self::CACHE_KEY);

            if (! $this->isValidIdList($ids)) {
                $ids = Plugin::query()
                    ->where('is_installed', true)
                    ->where('is_enabled', true)
                    ->pluck('id')
                    ->all();

                Cache::forever(self::CACHE_KEY, $ids);
            }

            return Plugin::query()->whereIn('id', $ids)->get();
        } catch (Throwable) {
            return collect();
        }
    }

    public function clear(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Resolve a packaged plugin's (GitHub or Composer) metadata from its
     * composer.json PSR-4 autoload map. Cached under a key that includes the
     * composer.json mtime, so any file change automatically invalidates it —
     * no explicit cache clearing needed on plugin updates.
     *
     * @return array{namespace: string, prefix: string, srcPath: string, source: string, path: string, version: ?string, name: ?string, description: ?string}|null
     */
    public function resolvePluginMetadata(string $source, string $name, string $path): ?array
    {
        $composerJsonPath = $path.DIRECTORY_SEPARATOR.'composer.json';

        if (! File::exists($composerJsonPath)) {
            return null;
        }

        $mtimeHash = substr(md5((string) File::lastModified($composerJsonPath)), 0, 8);
        $key = "plugin_meta:{$source}_{$name}_{$mtimeHash}";

        return Cache::remember($key, now()->addDays(self::METADATA_TTL_DAYS), function () use ($composerJsonPath, $path, $source) {
            $composer = json_decode(File::get($composerJsonPath), true) ?? [];
            $psr4 = $composer['autoload']['psr-4'] ?? [];

            foreach ($psr4 as $prefix => $relativePath) {
                $srcPath = trim($relativePath, '/\\');
                $pluginFile = implode(DIRECTORY_SEPARATOR, array_filter([$path, $srcPath, 'Plugin.php']));

                if (File::exists($pluginFile)) {
                    return [
                        'namespace' => rtrim($prefix, '\\').'\\Plugin',
                        'prefix' => rtrim($prefix, '\\').'\\',
                        'srcPath' => $srcPath,
                        'source' => $source,
                        'path' => $path,
                        'version' => $composer['version'] ?? null,
                        'name' => $composer['name'] ?? null,
                        'description' => $composer['description'] ?? null,
                    ];
                }
            }

            return null;
        });
    }

    /**
     * @param  Collection<int, Plugin>  $plugins
     */
    public function set(Collection $plugins): void
    {
        Cache::forever(self::CACHE_KEY, $plugins->pluck('id')->all());
    }

    /**
     * @phpstan-assert-if-true array<int, int> $value
     */
    private function isValidIdList(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $id) {
            if (! is_int($id)) {
                return false;
            }
        }

        return true;
    }
}
