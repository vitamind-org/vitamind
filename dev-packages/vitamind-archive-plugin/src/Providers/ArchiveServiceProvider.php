<?php

namespace VitaminD\Plugins\Archive\Providers;

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Gate;
use Spatie\RouteAttributes\RouteRegistrar;
use VitaminD\Plugins\Archive\Models\File;
use VitaminD\Plugins\Archive\Models\Folder;
use VitaminD\Plugins\Archive\Policies\FilePolicy;
use VitaminD\Plugins\Archive\Policies\FolderPolicy;
use VitaminD\PluginSdk\PluginBase;
use VitaminD\PluginSdk\RegisterPage;

/**
 * Unlike WorkspaceServiceProvider/RealtimeServiceProvider, nothing here is
 * gated behind a `vitamin-d.features.*` flag — the plugin as a whole is
 * always available. Only its `workspace` visibility tier depends on
 * `vitamin-d.features.workspaces`, and that is enforced inside
 * FolderPolicy/FilePolicy (via WorkspaceMembership::check()), not here.
 */
class ArchiveServiceProvider extends PluginBase
{
    /**
     * @return array{key: string, name: string, description: string}
     */
    public function pluginDetails(): array
    {
        return [
            'key' => 'archive',
            'name' => 'Archive Plugin',
            'description' => 'Folder and file storage with per-item visibility control.',
        ];
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/archive-plugin.php', 'archive-plugin');
    }

    public function boot(): void
    {
        $this->registerMigrations();
        $this->registerPolicies();
        $this->registerRoutes();
        $this->registerMenu();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Folder::class, FolderPolicy::class);
        Gate::policy(File::class, FilePolicy::class);
    }

    /**
     * Registers the "Archive" main-sidebar entry via the SDK's custom-link
     * `RegisterPage` mode — the plugin's own `archive.index` route and its
     * own Inertia page, not the generic `tabs()`/`plugins.page` CRUD screen
     * (the folder/file hierarchy has no `RegisterDataTable`-representable
     * shape). This is the plugin's only means of reaching the sidebar; see
     * docs/plugin-development/menu-registration.md.
     */
    protected function registerMenu(): void
    {
        RegisterPage::make('archive')
            ->title('Archive')
            ->icon('archive')
            ->route('archive.index')
            ->adminOnly(false)
            ->register();
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    /**
     * Registers the plugin's own attribute-routed controllers directly,
     * rather than relying on the consuming app's
     * `config/route-attributes.php` to know this package's internal
     * directory structure.
     */
    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        // realpath() matters here — see WorkspaceServiceProvider::registerRoutes()
        // for why: `vendor/vitamind/archive-plugin` is a symlink under the
        // dev-packages/ path-repository setup, and __DIR__ resolves through it.
        $controllersPath = realpath(__DIR__.'/../Http/Controllers');

        if (! $controllersPath) {
            return;
        }

        $registrar = new RouteRegistrar($this->app['router']);
        $registrar
            ->useMiddleware([SubstituteBindings::class])
            ->useRootNamespace('VitaminD\\Plugins\\Archive\\Http\\Controllers')
            ->useBasePath($controllersPath)
            ->group(['middleware' => 'web'], fn () => $registrar->registerDirectory($controllersPath, ['*Controller.php']));
    }
}
