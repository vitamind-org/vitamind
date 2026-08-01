<?php

namespace VitaminD\Core\Providers;

use VitaminD\Core\Actions\Plugins\BootPlugins;
use VitaminD\Core\Actions\Plugins\DiscoverPlugins;
use VitaminD\Core\Actions\Plugins\GetPluginInstance;
use VitaminD\Core\Console\Commands\EnablePluginCommand;
use VitaminD\Core\Console\Commands\InstallGithubPluginCommand;
use VitaminD\Core\Policies\UserPolicy;
use VitaminD\PluginSdk\RegisterCommand;
use VitaminD\PluginSdk\RegisterViews;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Spatie\RouteAttributes\RouteRegistrar;
use App\Models\PersonalAccessToken;
use App\Models\User;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(GetPluginInstance::class, function () {
            return new GetPluginInstance;
        });
    }

    public function boot(): void
    {
        JsonResource::withoutWrapping();
        Vite::prefetch(concurrency: 3);
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->app['router']->aliasMiddleware('must-be-admin', \VitaminD\Core\Http\Middleware\MustBeAdminMiddleware::class);
        Gate::policy(User::class, UserPolicy::class);
        $this->registerRoutes();

        $this->app->booted(function () {
            app(DiscoverPlugins::class)->handle();
            app(BootPlugins::class)->handle();

            foreach (RegisterViews::get() as $name => $path) {
                $this->loadViewsFrom($path, $name);
            }

            if ($this->app->runningInConsole()) {
                $this->commands([InstallGithubPluginCommand::class, EnablePluginCommand::class]);

                $commands = RegisterCommand::get();
                if (count($commands) > 0) {
                    $this->commands($commands);
                }
            }
        });
    }

    /**
     * Registers Core's own attribute-routed controllers directly, rather
     * than relying on the consuming app's `config/route-attributes.php` to
     * know Core's internal directory structure — otherwise a fresh
     * `composer require vitamind/core` installs the classes but none of
     * their routes.
     */
    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        // realpath() matters: when installed via a Composer path repository
        // (as in this monorepo's dev-packages/ setup), `vendor/vitamind/core`
        // is a symlink, so __DIR__ resolves through it — but the route
        // registrar matches file paths via SplFileInfo::getRealPath(), which
        // resolves symlinks. Without realpath() here the two never match and
        // every controller in the directory is silently dropped.
        $controllersPath = realpath(__DIR__.'/../Http/Controllers');
        $apiControllersPath = realpath(__DIR__.'/../Http/Controllers/API');

        $registrar = new RouteRegistrar($this->app['router']);
        $registrar->useMiddleware([SubstituteBindings::class]);

        if ($controllersPath) {
            $registrar
                ->useRootNamespace('VitaminD\\Core\\Http\\Controllers')
                ->useBasePath($controllersPath)
                ->group(['middleware' => 'web'], fn () => $registrar->registerDirectory($controllersPath, ['*Controller.php'], ['API/*']));
        }

        if ($apiControllersPath) {
            $registrar
                ->useRootNamespace('VitaminD\\Core\\Http\\Controllers\\API')
                ->useBasePath($apiControllersPath)
                ->group(['middleware' => 'api'], fn () => $registrar->registerDirectory($apiControllersPath, ['*Controller.php']));
        }
    }
}
