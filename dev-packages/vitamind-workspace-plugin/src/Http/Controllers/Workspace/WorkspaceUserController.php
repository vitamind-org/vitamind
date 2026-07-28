<?php

namespace VitaminD\Plugins\Workspace\Http\Controllers\Workspace;

use VitaminD\Core\Enums\UserRole;
use VitaminD\Core\Http\Controllers\Controller;
use VitaminD\Plugins\Workspace\Actions\Workspaces\InviteToWorkspace;
use VitaminD\Plugins\Workspace\Models\Workspace;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('settings/workspaces/{workspace}/users')]
#[Middleware(['auth'])]
class WorkspaceUserController extends Controller
{
    #[Post('/', name: 'workspaces.users.store')]
    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        app(InviteToWorkspace::class)->invite($workspace, $request->input());

        return back()->with('success', __('An invitation has been sent to the email address.'));
    }

    #[Delete('{id}', name: 'workspaces.users.destroy')]
    public function destroy(Workspace $workspace, int $id): RedirectResponse
    {
        $this->authorize('update', $workspace);

        /** @var ?UserWorkspace $userWorkspace */
        $userWorkspace = $workspace->users()->where('id', $id)->first();

        if ($userWorkspace?->user && $workspace->role($userWorkspace->user) === UserRole::OWNER) {
            return back()->with('error', __('You cannot remove the workspace owner.'));
        }

        if ($userWorkspace?->email === user()->email || $userWorkspace?->user_id === user()->id) {
            return back()->with('error', __('You cannot remove yourself from the workspace.'));
        }

        $workspace->users()
            ->where('id', $id)
            ->delete();

        return back()->with('success', __('The user has been removed.'));
    }
}
