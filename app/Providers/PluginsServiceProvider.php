<?php

namespace App\Providers;

use App\Actions\Plugins\BootPlugins;
use App\Actions\Plugins\DiscoverPlugins;
use App\Actions\Plugins\GetPluginInstance;
use VitaminD\PluginSdk\RegisterCommand;
use VitaminD\PluginSdk\RegisterViews;
use Illuminate\Support\ServiceProvider;

class PluginsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(GetPluginInstance::class, function () {
            return new GetPluginInstance;
        });
    }

    public function boot(): void
    {
        $this->app->booted(function () {
            // Automatically discover plugins first
            app(DiscoverPlugins::class)->handle();

            // Then boot them
            app(BootPlugins::class)->handle();

            // Load registered views
            foreach (RegisterViews::get() as $name => $path) {
                $this->loadViewsFrom($path, $name);
            }

            // Register console commands
            if ($this->app->runningInConsole()) {
                $commands = RegisterCommand::get();
                if (count($commands) > 0) {
                    $this->commands($commands);
                }
            }
        });
    }
}
