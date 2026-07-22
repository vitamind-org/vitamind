<?php

namespace VitaminD\Plugins\Workspace\Http\Controllers\Workspace;

use VitaminD\Plugins\Workspace\Http\Controllers\Controller;
use VitaminD\Plugins\Workspace\Models\Workspace;
use VitaminD\Core\Models\UserWorkspace;
use Illuminate\Http\RedirectResponse;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('settings/workspaces')]
#[Middleware(['auth'])]
class AcceptWorkspaceInviteController extends Controller
{
    #[Get('/{workspace}/invitations/accept', name: 'workspaces.invitations.accept')]
    public function __invoke(Workspace $workspace): RedirectResponse
    {
        /** @var ?UserWorkspace $userWorkspace */
        $userWorkspace = $workspace->users()->where('email', user()->email)->first();
        if (! $userWorkspace) {
            abort(404);
        }

        $userWorkspace->email = null;
        $userWorkspace->user_id = user()->id;
        $userWorkspace->save();

        return redirect()->route('workspaces')->with('success', __('You joined the workspace successfully.'));
    }
}
