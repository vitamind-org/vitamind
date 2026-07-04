<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\UserWorkspace;
use Illuminate\Http\RedirectResponse;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('settings/workspaces')]
#[Middleware(['auth'])]
class LeaveWorkspaceController extends Controller
{
    #[Delete('/{workspace}/leave', name: 'workspaces.leave')]
    public function __invoke(Workspace $workspace): RedirectResponse
    {
        /** @var ?UserWorkspace $userWorkspace */
        $userWorkspace = $workspace->users()
            ->where('user_id', user()->id)
            ->orWhere('email', user()->email)
            ->first();
        if (! $userWorkspace) {
            abort(404);
        }

        $userWorkspace->delete();

        return back()->with('success', __('You left the workspace successfully.'));
    }
}
