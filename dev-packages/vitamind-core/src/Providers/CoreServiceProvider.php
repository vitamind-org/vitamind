<?php

namespace VitaminD\Core\Providers;

use VitaminD\Core\Actions\Plugins\BootPlugins;
use VitaminD\Core\Actions\Plugins\DiscoverPlugins;
use VitaminD\Core\Actions\Plugins\GetPluginInstance;
use VitaminD\PluginSdk\RegisterCommand;
use VitaminD\PluginSdk\RegisterViews;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use VitaminD\Core\Models\PersonalAccessToken;

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

        $this->app->booted(function () {
            app(DiscoverPlugins::class)->handle();
            app(BootPlugins::class)->handle();

            foreach (RegisterViews::get() as $name => $path) {
                $this->loadViewsFrom($path, $name);
            }

            if ($this->app->runningInConsole()) {
                $commands = RegisterCommand::get();
                if (count($commands) > 0) {
                    $this->commands($commands);
                }
            }
        });
    }
}
