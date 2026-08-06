<?php

namespace VitaminD\Plugins\Workspace\Http\Controllers\Workspace;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Prefix;
use VitaminD\Core\Http\Controllers\Controller;
use VitaminD\Plugins\Workspace\Actions\Workspaces\AcceptWorkspaceInvite;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;
use VitaminD\Plugins\Workspace\Models\Workspace;

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

        return redirect()->route($this->hasAccountFor($invite->email) ? 'login' : 'register');
    }

    /**
     * A recipient who already has an account can't register with the
     * invited email (it's taken), so send them to login instead — the
     * `Login` listener in WorkspaceServiceProvider consumes the same
     * `pending_invite_id` session value once they're authenticated.
     */
    private function hasAccountFor(?string $email): bool
    {
        if (! $email) {
            return false;
        }

        $userModel = config('auth.providers.users.model');

        return $userModel::query()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->exists();
    }
}
