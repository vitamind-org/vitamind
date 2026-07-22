<?php

namespace VitaminD\Core\Http\Middleware;

use VitaminD\Core\Models\PersonalAccessToken;
use VitaminD\Core\Models\Workspace;
use VitaminD\Core\Models\User;
use Closure;
use Illuminate\Http\Request;

class CanSeeWorkspaceMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Workspace $workspace */
        $workspace = $request->route('workspace');

        if (! $user->can('view', $workspace)) {
            abort(403, 'You do not have permission to view this workspace.');
        }

        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken && $token->exists && ! $token->hasWorkspaceAccess($workspace)) {
            abort(403, 'This token does not have access to this workspace.');
        }

        return $next($request);
    }
}
