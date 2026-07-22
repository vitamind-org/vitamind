<?php

namespace VitaminD\Plugins\Workspace\Providers;

use Illuminate\Support\ServiceProvider;

class WorkspaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!config('vitamin-d.features.workspaces')) {
            return;
        }
    }

    public function boot(): void
    {
        if (!config('vitamin-d.features.workspaces')) {
            return;
        }

        $this->registerRoutes();
        $this->registerMigrations();
    }

    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $routePath = __DIR__ . '/../../routes/api.php';
        if (file_exists($routePath)) {
            $this->loadRoutesFrom($routePath);
        }
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
