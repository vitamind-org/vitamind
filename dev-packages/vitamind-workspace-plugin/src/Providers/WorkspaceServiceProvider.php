<?php

namespace VitaminD\Plugins\Workspace\Providers;

use VitaminD\Core\Support\InertiaSharedData;
use VitaminD\Plugins\Workspace\Http\Middleware\CanSeeWorkspaceMiddleware;
use VitaminD\Plugins\Workspace\Http\Middleware\HasWorkspaceMiddleware;
use VitaminD\Plugins\Workspace\Http\Resources\WorkspaceResource;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

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
    }

    protected function registerMiddlewareAliases(): void
    {
        $this->app['router']->aliasMiddleware('has-workspace', HasWorkspaceMiddleware::class);
        $this->app['router']->aliasMiddleware('can-see-workspace', CanSeeWorkspaceMiddleware::class);
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
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
