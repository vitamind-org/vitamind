<?php

namespace VitaminD\Core\Providers;

use VitaminD\Core\Actions\Plugins\BootPlugins;
use VitaminD\Core\Actions\Plugins\DiscoverPlugins;
use VitaminD\Core\Actions\Plugins\GetPluginInstance;
use VitaminD\Core\Console\Commands\InstallGithubPluginCommand;
use VitaminD\Core\Policies\UserPolicy;
use VitaminD\PluginSdk\RegisterCommand;
use VitaminD\PluginSdk\RegisterViews;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
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

        $this->app->booted(function () {
            app(DiscoverPlugins::class)->handle();
            app(BootPlugins::class)->handle();

            foreach (RegisterViews::get() as $name => $path) {
                $this->loadViewsFrom($path, $name);
            }

            if ($this->app->runningInConsole()) {
                $this->commands([InstallGithubPluginCommand::class]);

                $commands = RegisterCommand::get();
                if (count($commands) > 0) {
                    $this->commands($commands);
                }
            }
        });
    }
}
