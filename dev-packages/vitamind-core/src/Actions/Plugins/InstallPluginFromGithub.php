<?php

namespace VitaminD\Core\Actions\Plugins;

use VitaminD\Core\Enums\PluginSource;
use VitaminD\Core\Models\Plugin;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Installs a plugin by cloning a GitHub repository directly into
 * storage/plugins, for testing or using community/uncommercial plugins
 * without a Packagist release. See InstallGithubPlugin for the
 * release-based installer used by the admin UI.
 */
final readonly class InstallPluginFromGithub
{
    public function __construct(
        private PluginCache $cache,
        private InstallPlugin $installPlugin,
    ) {}

    /**
     * @throws Exception
     */
    public function handle(string $repository, ?string $branch = null): Plugin
    {
        if (! preg_match('#^[\w.-]+/[\w.-]+$#', $repository)) {
            throw new Exception("Invalid repository format, expected '{org}/{name}'.");
        }

        $folder = $this->toPsrCase(explode('/', $repository)[1]);

        if (Plugin::where('folder', $folder)->where('source', PluginSource::GITHUB)->exists()) {
            throw new Exception("A GitHub plugin named '{$folder}' is already installed.");
        }

        $destination = plugins_path($folder);
        if (File::isDirectory($destination)) {
            throw new Exception("Destination '{$destination}' already exists.");
        }

        $this->clone($repository, $branch, $destination);

        try {
            $metadata = $this->validate($destination, $folder);

            File::deleteDirectory($destination.DIRECTORY_SEPARATOR.'.git');

            $plugin = Plugin::create([
                'folder' => $folder,
                'namespace' => $metadata['namespace'],
                'source' => PluginSource::GITHUB,
                'repo' => "https://github.com/{$repository}",
                'username' => explode('/', $repository)[0],
                'version' => $branch ?? $metadata['version'],
                'is_installed' => false,
                'is_enabled' => false,
            ]);

            $this->installPlugin->handle($plugin);

            return $plugin;
        } catch (Throwable $ex) {
            // Roll back: any failure past this point must leave no partial state.
            File::deleteDirectory($destination);
            Plugin::where('folder', $folder)->where('source', PluginSource::GITHUB)->delete();
            $this->cache->clear();

            throw $ex instanceof Exception ? $ex : new Exception($ex->getMessage(), previous: $ex);
        }
    }

    /**
     * @throws Exception
     */
    private function clone(string $repository, ?string $branch, string $destination): void
    {
        $git = git_path();
        if (! $git) {
            throw new Exception('git binary not found on this system.');
        }

        $command = [$git, 'clone', '--depth', '1'];
        if ($branch) {
            $command[] = '--branch';
            $command[] = $branch;
        }
        $command[] = "https://github.com/{$repository}.git";
        $command[] = $destination;

        $result = Process::timeout(120)->run($command);

        if (! $result->successful()) {
            File::deleteDirectory($destination);
            throw new Exception('Failed to clone repository: '.trim($result->errorOutput()));
        }
    }

    /**
     * @return array{namespace: string, version: ?string}
     *
     * @throws Exception
     */
    private function validate(string $destination, string $folder): array
    {
        if (! File::exists($destination.DIRECTORY_SEPARATOR.'composer.json')) {
            throw new Exception('Invalid plugin: composer.json not found in repository root.');
        }

        $metadata = $this->cache->resolvePluginMetadata(PluginSource::GITHUB->value, $folder, $destination);

        if ($metadata === null) {
            throw new Exception('Invalid plugin: composer.json PSR-4 autoload entry pointing to a Plugin.php class was not found.');
        }

        return $metadata;
    }

    private function toPsrCase(string $string): string
    {
        $string = str_replace(['-', '_', ' '], ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);

        return ucfirst($string);
    }
}
