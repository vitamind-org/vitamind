<?php

namespace VitaminD\Plugins\Workspace\Http\Controllers\Workspace;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Prefix;
use VitaminD\Core\Http\Controllers\Controller;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;
use VitaminD\Plugins\Workspace\Models\Workspace;

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

        $wasDefault = $userWorkspace->is_default;
        $userId = $userWorkspace->user_id;

        DB::transaction(function () use ($userWorkspace, $wasDefault, $userId): void {
            $userWorkspace->delete();

            if ($wasDefault && $userId) {
                UserWorkspace::promoteOldestDefaultFor($userId);
            }
        });

        return back()->with('success', __('You left the workspace successfully.'));
    }
}
