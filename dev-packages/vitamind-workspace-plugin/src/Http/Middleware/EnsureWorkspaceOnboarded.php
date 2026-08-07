<?php

namespace VitaminD\Plugins\Workspace\Http\Middleware;

use VitaminD\Core\Models\User;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;
use Closure;
use Illuminate\Http\Request;

/**
 * Routes any authenticated user with zero accepted `user_workspace`
 * memberships to the onboarding/choice screen, replacing the retired
 * silent default-workspace auto-creation. Applies uniformly to invited
 * and organic signups alike — but only to workspace-scoped areas of the
 * app. Account-level settings (profile, API keys, password, email
 * verification), the admin panel, and generic plugin dynamic pages are
 * not inherently tied to having a workspace, so they stay reachable
 * regardless of membership state.
 */
class EnsureWorkspaceOnboarded
{
    protected array $exceptRouteNames = [
        'workspaces.onboarding',
        'workspaces.onboarding.accept',
        'workspaces.invitations.accept',
        // The onboarding screen's create-workspace form posts here directly
        // (see WorkspaceOnboardingController / WorkspaceController::store) —
        // it must stay reachable by a user with zero memberships, otherwise
        // they could never create their first workspace.
        'workspaces.store',
        'logout',
        'profile*',
        'api-keys*',
        'password.*',
        'verification.*',
    ];

    protected array $exceptPaths = [
        // Admin panel manages the system (users, plugins) independently of
        // any per-user workspace membership.
        'admin/*',
        // Generic plugin dynamic pages aren't inherently workspace-scoped —
        // some plugins add their own workspace_id scoping, others don't.
        'p/*',
        // The POST handler for password confirmation is unnamed
        // (routes/auth.php), so it can't be matched by route name.
        'confirm-password',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        /** @var ?User $user */
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($request->routeIs(...$this->exceptRouteNames) || $request->is(...$this->exceptPaths)) {
            return $next($request);
        }

        if (UserWorkspace::query()->where('user_id', $user->id)->exists()) {
            return $next($request);
        }

        return redirect()->route('workspaces.onboarding');
    }
}
