<?php

namespace VitaminD\Plugins\Workspace\Providers;

use VitaminD\Core\Events\UserRemoving;
use VitaminD\Core\Support\InertiaSharedData;
use VitaminD\Plugins\Workspace\Http\Middleware\CanSeeWorkspaceMiddleware;
use VitaminD\Plugins\Workspace\Http\Middleware\HasWorkspaceMiddleware;
use VitaminD\Plugins\Workspace\Http\Resources\WorkspaceResource;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\RouteAttributes\RouteRegistrar;

class WorkspaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! config('vitamin-d.features.workspaces')) {
            return;
        }
    }

    public function boot(): void
    {
        if (! config('vitamin-d.features.workspaces')) {
            return;
        }

        $this->registerMiddlewareAliases();
        $this->registerMigrations();
        $this->registerInertiaSharedData();
        $this->registerRoutes();
        $this->registerEventListeners();
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

        // realpath() matters here — see CoreServiceProvider::registerRoutes()
        // for why: `vendor/vitamind/workspace-plugin` is a symlink under the
        // dev-packages/ path-repository setup, and __DIR__ resolves through it.
        $controllersPath = realpath(__DIR__ . '/../Http/Controllers');

        if (! $controllersPath) {
            return;
        }

        $registrar = new RouteRegistrar($this->app['router']);
        $registrar
            ->useMiddleware([SubstituteBindings::class])
            ->useRootNamespace('VitaminD\\Plugins\\Workspace\\Http\\Controllers')
            ->useBasePath($controllersPath)
            ->group(['middleware' => 'web'], fn() => $registrar->registerDirectory($controllersPath, ['*Controller.php']));
    }

    protected function registerMiddlewareAliases(): void
    {
        $this->app['router']->aliasMiddleware('has-workspace', HasWorkspaceMiddleware::class);
        $this->app['router']->aliasMiddleware('can-see-workspace', CanSeeWorkspaceMiddleware::class);
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    /**
     * Cleans up workspace membership when a user is removed via core's
     * admin panel. Listens on core's own domain event rather than an
     * Eloquent model event, since Eloquent scopes model events to the
     * exact runtime class — a listener registered against
     * `VitaminD\Core\Models\User` would never fire for `App\Models\User`
     * instances. Core dispatches `UserRemoving` explicitly regardless of
     * which concrete User subclass is involved, so this fires either way.
     */
    protected function registerEventListeners(): void
    {
        Event::listen(UserRemoving::class, function (UserRemoving $event): void {
            UserWorkspace::query()->where('user_id', $event->user->id)->delete();
        });
    }

    protected function registerInertiaSharedData(): void
    {
        InertiaSharedData::extend(function (Request $request): array {
            $user = $request->user();

            if (! $user) {
                return [];
            }

            $currentWorkspace = $user->currentWorkspace;
            $canSeeCurrentWorkspace = $currentWorkspace && $user->can('view', $currentWorkspace);
            if (! $currentWorkspace || ! $canSeeCurrentWorkspace) {
                $user->ensureHasDefaultWorkspace();
                $user->unsetRelation('currentWorkspace');
                $currentWorkspace = $user->currentWorkspace;
            }

            return [
                'auth' => [
                    'currentWorkspace' => $currentWorkspace ? WorkspaceResource::make($currentWorkspace) : null,
                ],
            ];
        });
    }
}
