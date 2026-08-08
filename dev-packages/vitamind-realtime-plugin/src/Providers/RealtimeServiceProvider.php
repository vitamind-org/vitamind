<?php

namespace VitaminD\Plugins\Realtime\Providers;

use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Broadcasting\BroadcastServiceProvider;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use VitaminD\Core\Events\PluginStateChanged;
use VitaminD\Plugins\Realtime\Events\BootstrapInvalidated;
use VitaminD\Plugins\Realtime\Support\WorkspaceChannelAuthorization;
use VitaminD\Plugins\Workspace\Http\Middleware\EnsureWorkspaceOnboarded;

class RealtimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! config('vitamin-d.features.websocket')) {
            return;
        }

        // The host app has no config/broadcasting.php and its bootstrap/app.php
        // never calls withBroadcasting(), so nothing binds
        // Illuminate\Contracts\Broadcasting\Factory on its own — done here,
        // during the register phase, so it's available before any provider's
        // boot() (including laravel/reverb's own Broadcast::extend() call)
        // runs, regardless of package auto-discovery order.
        $this->mergeConfigFrom(__DIR__.'/../../config/broadcasting.php', 'broadcasting');

        $this->app->register(BroadcastServiceProvider::class);
    }

    public function boot(): void
    {
        if (! config('vitamin-d.features.websocket')) {
            return;
        }

        $this->registerBroadcastAuthRoute();
        $this->registerChannels();
        $this->registerEventListeners();
    }

    /**
     * Registers Laravel's own `/broadcasting/auth` endpoint under the `web`
     * middleware group — session-based auth (the same guard every other
     * VitaminD route already uses), not a new auth mechanism.
     *
     * Registered by hand (mirroring `Broadcast::routes()` exactly, down to
     * excluding CSRF the same way it does) rather than calling that helper
     * directly, so `EnsureWorkspaceOnboarded` can be excluded on the same
     * route definition. That middleware is pushed onto the whole `web`
     * group by `vitamind/workspace-plugin`, when installed and enabled, and
     * would otherwise redirect any authenticated user with zero workspace
     * memberships away from this endpoint before `BroadcastController` ever
     * runs — discovered via the verification test this task requires.
     * Broadcasting auth isn't inherently workspace-scoped, the same
     * reasoning that middleware already applies to account settings and the
     * admin panel; `vitamind/realtime-plugin` still has no hard dependency
     * on `vitamind/workspace-plugin` (design.md's D3), hence the
     * `class_exists()` guard rather than a plain `use` of an assumed-present
     * class.
     */
    protected function registerBroadcastAuthRoute(): void
    {
        if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
            return;
        }

        $this->app['router']->group(['middleware' => ['web']], function ($router): void {
            $route = $router->match(
                ['get', 'post'], '/broadcasting/auth',
                '\\'.BroadcastController::class.'@authenticate'
            )->withoutMiddleware([PreventRequestForgery::class]);

            if (class_exists(EnsureWorkspaceOnboarded::class)) {
                $route->withoutMiddleware(EnsureWorkspaceOnboarded::class);
            }
        });
    }

    /**
     * Registers the shipped example's channel authorization. This is the
     * only channel this package itself defines — see
     * VitaminD\Plugins\Realtime\Events\WorkspacePing's docblock for why it
     * exists as a worked example rather than production surface.
     *
     * Explicit `guards` because Laravel's broadcaster otherwise only tries
     * the app's *default* auth guard (`web`, session-based) — a request
     * authenticated via a Sanctum API token would never reach the closure
     * below without `sanctum` listed here too.
     */
    protected function registerChannels(): void
    {
        Broadcast::channel('workspace.{workspaceId}.ping', function ($user, $workspaceId): bool {
            return WorkspaceChannelAuthorization::check($user, $workspaceId);
        }, ['guards' => ['web', 'sanctum']]);
    }

    /**
     * Reacts to core's existing PluginStateChanged event — already the
     * trigger for GetBootstrap::forgetVersion() on every plugin
     * install/enable/disable/uninstall — by re-broadcasting
     * BootstrapInvalidated. Core never gains a broadcasting dependency: this
     * listener lives entirely here, mirroring how
     * WorkspaceServiceProvider::registerEventListeners() reacts to core's
     * UserRemoving/Registered events without core knowing workspace-plugin
     * exists.
     */
    protected function registerEventListeners(): void
    {
        Event::listen(PluginStateChanged::class, function (): void {
            broadcast(new BootstrapInvalidated);
        });
    }
}
