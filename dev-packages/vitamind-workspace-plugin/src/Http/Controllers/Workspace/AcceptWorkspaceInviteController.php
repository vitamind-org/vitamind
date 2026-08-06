<?php

namespace VitaminD\Plugins\Workspace\Http\Controllers\Workspace;

use VitaminD\Core\Http\Controllers\Controller;
use VitaminD\Plugins\Workspace\Actions\Workspaces\AcceptWorkspaceInvite;
use VitaminD\Plugins\Workspace\Models\Workspace;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('settings/workspaces')]
#[Middleware(['signed'])]
class AcceptWorkspaceInviteController extends Controller
{
    #[Get('/{workspace}/invitations/{invite}/accept', name: 'workspaces.invitations.accept')]
    public function __invoke(Workspace $workspace, UserWorkspace $invite): RedirectResponse
    {
        if ($invite->workspace_id !== $workspace->id) {
            abort(404);
        }

        if (auth()->check()) {
            if (Str::lower((string) $invite->email) !== Str::lower(user()->email)) {
                abort(403);
            }

            app(AcceptWorkspaceInvite::class)->accept($invite, user());

            return redirect()->route('workspaces')->with('success', __('You joined the workspace successfully.'));
        }

        session(['pending_invite_id' => $invite->id]);

        return redirect()->route('register');
    }
}
