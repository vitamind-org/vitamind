<?php

namespace VitaminD\Core\Actions\Plugins\Github;

use VitaminD\Core\Actions\Plugins\InstallPlugin;
use VitaminD\Core\Actions\Plugins\PluginCache;
use VitaminD\Core\Enums\PluginSource;
use VitaminD\Core\Models\Plugin;
use Exception;
use Illuminate\Support\Facades\File;

final readonly class InstallGithubPlugin
{
    public function __construct(
        private GetReleaseInfo $releaseInfo,
        private DownloadRelease $downloadRelease,
        private ExtractPlugin $extractZip,
        private InstallPlugin $installPlugin,
        private PluginCache $cache,
    ) {}

    /**
     * @throws Exception
     */
    public function handle(string $url, ?Plugin $plugin = null): Plugin
    {
        if (str_contains($url, 'https://api.github.com/repos')) {
            $url = str_replace('https://api.github.com/repos', 'https://github.com', $url);
        }

        if ($plugin === null) {
            $existingPlugin = Plugin::where('repo', $url)->exists();
            if ($existingPlugin) {
                throw new Exception('Plugin is already installed');
            }
        }

        [$username, $repo] = $this->parseGitHubUrl($url);

        $release = $this->releaseInfo->handle($username, $repo);
        if ($release === null) {
            throw new Exception('Plugin has no released versions');
        }

        $psrRepo = $this->toPsrCase($repo);

        if ($plugin === null) {
            $existingPlugin = Plugin::where('folder', $psrRepo)->exists();
            if ($existingPlugin) {
                throw new Exception('A plugin with the same name is already installed');
            }
        }

        $zipFile = implode(DIRECTORY_SEPARATOR, ['app', 'temp', "$repo.zip"]);
        $zipLocation = storage_path($zipFile);
        $extractLocation = plugins_path($psrRepo);

        $this->downloadRelease->handle($release, $zipLocation);
        $this->extractZip->handle($zipLocation, $extractLocation);

        File::delete($zipLocation);

        $metadata = $this->cache->resolvePluginMetadata(PluginSource::GITHUB->value, $psrRepo, $extractLocation);
        if ($metadata === null) {
            File::deleteDirectory($extractLocation);
            throw new Exception('Invalid plugin: composer.json with a PSR-4 autoload entry pointing to a Plugin.php class was not found.');
        }

        if ($plugin === null) {
            $plugin = Plugin::updateOrCreate(
                ['folder' => $psrRepo],
                [
                    'repo' => $url,
                    'username' => $username,
                    'folder' => $psrRepo,
                    'version' => $release->tagName,
                    'namespace' => $metadata['namespace'],
                    'source' => PluginSource::GITHUB,
                    'is_installed' => false,
                    'is_enabled' => false,
                    'name' => null,
                    'description' => null,
                    'updates_available' => false,
                ]
            );

            $this->installPlugin->handle($plugin);
        } else {
            $plugin->version = $release->tagName;
            $plugin->namespace = $metadata['namespace'];
            $plugin->updates_available = false;
            $plugin->save();
        }

        $this->cache->clear();

        return $plugin;
    }

    /**
     * @throws Exception
     */
    private function parseGitHubUrl(string $url): array
    {
        $parsed = parse_url(rtrim($url, '.git'));
        if (($parsed['host'] ?? '') !== 'github.com') {
            throw new Exception("Invalid GitHub URL provided. $url");
        }

        $parts = explode('/', trim($parsed['path'], '/'));
        if (count($parts) < 2) {
            throw new Exception('Invalid GitHub repository URL format');
        }

        return [$parts[0], $parts[1]];
    }

    private function toPsrCase(string $string): string
    {
        $string = str_replace(['-', '_', ' '], ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);

        return ucfirst($string);
    }
}
