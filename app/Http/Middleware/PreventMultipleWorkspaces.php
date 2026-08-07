<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces WakuWaku's one-workspace-per-user product policy on routes that
 * `vitamind-workspace-plugin` doesn't run through an `authorize()` call
 * (e.g. invite acceptance), so it can't be reached via a `Gate::before`
 * hook. Registered globally on the `web` middleware group (see
 * `bootstrap/app.php`) rather than attached to the plugin's route object at
 * boot time: `vitamind-workspace-plugin`'s own routes aren't guaranteed to
 * be registered yet by the time any `booted()` callback fires (deferred
 * provider resolution), so a route-name check inside a normal middleware —
 * which only runs once the route has already been matched — is the
 * reliable option that doesn't depend on provider boot ordering.
 *
 * Both guarded routes accept an already-authenticated user accepting an
 * invitation while they already belong to a workspace:
 * `workspaces.invitations.accept` (signed-link click-through, also reachable
 * by guests — left untouched here since the plugin's own guest flow just
 * redirects to login/register) and `workspaces.onboarding.accept` (the
 * onboarding screen's per-invitation accept action).
 */
class PreventMultipleWorkspaces
{
    private const array GUARDED_ROUTES = [
        'workspaces.invitations.accept',
        'workspaces.onboarding.accept',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (! in_array($routeName, self::GUARDED_ROUTES, true)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && $user->allWorkspaces()->exists()) {
            abort(403, 'You already belong to a workspace.');
        }

        return $next($request);
    }
}
