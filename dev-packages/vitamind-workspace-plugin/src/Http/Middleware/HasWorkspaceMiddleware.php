<?php

namespace VitaminD\Plugins\Workspace\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class HasWorkspaceMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var ?User $user */
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (config('vitamin-d.features.workspaces', false) && ! $user->currentWorkspace) {
            if ($user->workspaces()->count() > 0) {
                $user->ensureHasDefaultWorkspace();
                $user->refresh();

                return redirect()->route('dashboard');
            }

            abort(403, 'You must have a workspace to access the panel.');
        }

        return $next($request);
    }
}
