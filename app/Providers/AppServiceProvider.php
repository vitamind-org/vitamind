<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use VitaminD\Core\Support\InertiaSharedData;
use VitaminD\Plugins\Workspace\Models\Workspace;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerSingleWorkspaceTenancy();
    }

    /**
     * WakuWaku restricts every user to exactly one workspace, enforced
     * entirely at this application layer so `vitamind-workspace-plugin`
     * (which defaults to multi-workspace-per-user) stays unmodified for
     * other VitaminD-based projects. See
     * openspec/changes/provision-workspace-whatsapp-instances/specs/single-workspace-tenancy/spec.md.
     */
    private function registerSingleWorkspaceTenancy(): void
    {
        if (! config('vitamin-d.features.workspaces')) {
            return;
        }

        // Workspace creation goes through WorkspaceController::store(),
        // which calls `$this->authorize('create', Workspace::class)` —
        // Gate::before intercepts that check before WorkspacePolicy::create()
        // (which always returns true) ever runs.
        Gate::before(function ($user, string $ability, array $arguments = []) {
            if ($ability !== 'create' || ($arguments[0] ?? null) !== Workspace::class) {
                return null;
            }

            if (! $user instanceof User) {
                return null;
            }

            return $user->allWorkspaces()->exists() ? false : null;
        });

        InertiaSharedData::extend(function (Request $request): array {
            $user = $request->user();

            if (! $user instanceof User) {
                return [];
            }

            return [
                'auth' => [
                    'hasWorkspace' => $user->allWorkspaces()->exists(),
                ],
            ];
        });

        // Invite acceptance (AcceptWorkspaceInviteController) never calls
        // authorize(), so Gate::before can't reach it — see
        // App\Http\Middleware\PreventMultipleWorkspaces, registered globally
        // on the `web` group in bootstrap/app.php, for that enforcement.
    }
}
