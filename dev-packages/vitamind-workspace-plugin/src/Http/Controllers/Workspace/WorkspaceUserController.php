<?php

namespace VitaminD\Plugins\Workspace\Http\Controllers\Workspace;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use VitaminD\Core\Http\Controllers\Controller;
use VitaminD\Plugins\Workspace\Actions\Workspaces\InviteToWorkspace;
use VitaminD\Plugins\Workspace\Actions\Workspaces\ResendWorkspaceInvitation;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;
use VitaminD\Plugins\Workspace\Models\Workspace;
use VitaminD\Plugins\Workspace\Support\WorkspaceRoles;

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

        if (! $userWorkspace) {
            abort(404);
        }

        if ($userWorkspace?->user_id !== null && $userWorkspace->user_id === $workspace->owner_id) {
            return back()->with('error', __('You cannot remove the workspace owner.'));
        }

        if ($userWorkspace?->email === user()->email || $userWorkspace?->user_id === user()->id) {
            return back()->with('error', __('You cannot remove yourself from the workspace.'));
        }

        $wasDefault = (bool) $userWorkspace?->is_default;
        $userId = $userWorkspace?->user_id;

        DB::transaction(function () use ($workspace, $id, $wasDefault, $userId): void {
            $workspace->users()
                ->where('id', $id)
                ->delete();

            if ($userId) {
                WorkspaceRoles::clearFor($userId, $workspace->id);
            }

            if ($wasDefault && $userId) {
                UserWorkspace::promoteOldestDefaultFor($userId);
            }
        });

        return back()->with('success', __('The user has been removed.'));
    }

    #[Post('{id}/resend', name: 'workspaces.users.resend')]
    public function resend(Workspace $workspace, int $id): RedirectResponse
    {
        $this->authorize('update', $workspace);

        /** @var ?UserWorkspace $userWorkspace */
        $userWorkspace = $workspace->users()->whereNull('user_id')->where('id', $id)->first();

        if (! $userWorkspace) {
            abort(404);
        }

        app(ResendWorkspaceInvitation::class)->resend($userWorkspace);

        return back()->with('success', __('The invitation has been resent.'));
    }
}
