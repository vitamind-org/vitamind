<?php

namespace VitaminD\Plugins\Workspace\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\RouteAttributes\RouteRegistrar;
use VitaminD\Core\Events\UserRemoving;
use VitaminD\Core\Support\InertiaSharedData;
use VitaminD\Plugins\Workspace\Actions\Workspaces\AcceptWorkspaceInvite;
use VitaminD\Plugins\Workspace\Http\Middleware\CanSeeWorkspaceMiddleware;
use VitaminD\Plugins\Workspace\Http\Middleware\EnsureWorkspaceOnboarded;
use VitaminD\Plugins\Workspace\Http\Middleware\HasWorkspaceMiddleware;
use VitaminD\Plugins\Workspace\Http\Resources\WorkspaceResource;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;

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
        $this->registerGlobalMiddleware();
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
        $controllersPath = realpath(__DIR__.'/../Http/Controllers');

        if (! $controllersPath) {
            return;
        }

        $registrar = new RouteRegistrar($this->app['router']);
        $registrar
            ->useMiddleware([SubstituteBindings::class])
            ->useRootNamespace('VitaminD\\Plugins\\Workspace\\Http\\Controllers')
            ->useBasePath($controllersPath)
            ->group(['middleware' => 'web'], fn () => $registrar->registerDirectory($controllersPath, ['*Controller.php']));
    }

    protected function registerMiddlewareAliases(): void
    {
        $this->app['router']->aliasMiddleware('has-workspace', HasWorkspaceMiddleware::class);
        $this->app['router']->aliasMiddleware('can-see-workspace', CanSeeWorkspaceMiddleware::class);
    }

    /**
     * Applied globally to the `web` group (rather than per-route) so it
     * catches every authenticated request site-wide, matching the "any
     * request from a user with zero memberships is redirected" guard —
     * the middleware itself no-ops for guests and for its own excepted
     * routes.
     */
    protected function registerGlobalMiddleware(): void
    {
        $this->app['router']->pushMiddlewareToGroup('web', EnsureWorkspaceOnboarded::class);
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
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

        // Only the single invitation referenced by the signed link the visitor
        // actually clicked (see AcceptWorkspaceInviteController) is ever
        // auto-accepted here — never a bulk match against every pending
        // invitation sharing the registrant's email.
        Event::listen(Registered::class, function (Registered $event): void {
            $inviteId = session('pending_invite_id');
            session()->forget('pending_invite_id');

            if (! $inviteId) {
                return;
            }

            /** @var ?UserWorkspace $invite */
            $invite = UserWorkspace::query()->whereNull('user_id')->find($inviteId);

            if (! $invite) {
                return;
            }

            if (Str::lower((string) $invite->email) !== Str::lower($event->user->email)) {
                // Registrant used a different email than the one invited —
                // stash what they need to know so the onboarding screen can
                // explain why the invite wasn't auto-accepted, instead of
                // failing silently. Read-and-cleared by
                // WorkspaceOnboardingController on its next render.
                session()->put('invite_email_mismatch', [
                    'email' => $invite->email,
                    'workspace_name' => $invite->workspace?->name,
                ]);

                return;
            }

            app(AcceptWorkspaceInvite::class)->accept($invite, $event->user);
        });

        // Mirrors the Registered listener above, for the guest-with-an-
        // existing-account path: AcceptWorkspaceInviteController sends that
        // visitor to login (not register) but still stashes
        // `pending_invite_id`, so the invite has to be consumed here too.
        Event::listen(Login::class, function (Login $event): void {
            $inviteId = session('pending_invite_id');
            session()->forget('pending_invite_id');

            if (! $inviteId) {
                return;
            }

            /** @var ?UserWorkspace $invite */
            $invite = UserWorkspace::query()->whereNull('user_id')->find($inviteId);

            if (! $invite) {
                return;
            }

            if (Str::lower((string) $invite->email) !== Str::lower($event->user->email)) {
                session()->put('invite_email_mismatch', [
                    'email' => $invite->email,
                    'workspace_name' => $invite->workspace?->name,
                ]);

                return;
            }

            app(AcceptWorkspaceInvite::class)->accept($invite, $event->user);
        });
    }

    protected function registerInertiaSharedData(): void
    {
        InertiaSharedData::extend(function (Request $request): array {
            $user = $request->user();

            if (! $user) {
                return $this->pendingInviteSharedData();
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

    /**
     * Exposes the workspace/email a guest is about to join when they arrived
     * via a signed invitation link (see AcceptWorkspaceInviteController),
     * so the registration form can pre-fill and lock the email field rather
     * than letting a typo/different-email registration silently miss the
     * invite.
     *
     * @return array<string, mixed>
     */
    private function pendingInviteSharedData(): array
    {
        $inviteId = session('pending_invite_id');

        if (! $inviteId) {
            return [];
        }

        /** @var ?UserWorkspace $invite */
        $invite = UserWorkspace::query()->whereNull('user_id')->find($inviteId);

        if (! $invite) {
            return [];
        }

        return [
            'pendingInvite' => [
                'email' => $invite->email,
                'workspaceName' => $invite->workspace?->name,
            ],
        ];
    }
}
