<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\URL;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Patch;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('settings/workspaces')]
#[Middleware(['auth'])]
class WorkspaceSwitchController extends Controller
{
    #[Patch('switch/{workspace}', name: 'workspaces.switch')]
    public function __invoke(Workspace $workspace): RedirectResponse
    {
        $this->authorize('view', $workspace);

        user()->update([
            'current_workspace_id' => $workspace->id,
        ]);

        $previousUrl = URL::previous();
        $previousRequest = Request::create($previousUrl);
        $previousRoute = app('router')->getRoutes()->match($previousRequest);

        if (count($previousRoute->parameters()) > 0) {
            return redirect()->route('dashboard');
        }

        return redirect()->route($previousRoute->getName());
    }
}
