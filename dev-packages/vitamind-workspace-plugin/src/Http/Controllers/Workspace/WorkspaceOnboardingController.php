<?php

namespace VitaminD\Plugins\Workspace\Http\Controllers\Workspace;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use VitaminD\Core\Http\Controllers\Controller;
use VitaminD\Plugins\Workspace\Actions\Workspaces\AcceptWorkspaceInvite;
use VitaminD\Plugins\Workspace\Http\Resources\WorkspaceUserResource;
use VitaminD\Plugins\Workspace\Models\UserWorkspace;

#[Prefix('settings/workspaces/onboarding')]
#[Middleware(['auth'])]
class WorkspaceOnboardingController extends Controller
{
    #[Get('/', name: 'workspaces.onboarding')]
    public function index(): Response
    {
        // Set by the Registered listener when the visitor registered with a
        // different email than the one an invite link referenced — shown
        // once so they understand why nothing was auto-accepted, then
        // cleared so it doesn't linger on later visits.
        $emailMismatch = session('invite_email_mismatch');
        session()->forget('invite_email_mismatch');

        return Inertia::render('workspaces/onboarding', [
            'invitations' => WorkspaceUserResource::collection(
                UserWorkspace::query()
                    ->with(['user', 'workspace'])
                    ->whereRaw('LOWER(email) = ?', [Str::lower(user()->email)])
                    ->whereNull('user_id')
                    ->get()
            ),
            'suggestedWorkspaceName' => $this->suggestedWorkspaceName(user()->name),
            'emailMismatch' => $emailMismatch,
        ]);
    }

    #[Post('/{invite}/accept', name: 'workspaces.onboarding.accept')]
    public function accept(UserWorkspace $invite): RedirectResponse
    {
        if (Str::lower((string) $invite->email) !== Str::lower(user()->email)) {
            abort(403);
        }

        app(AcceptWorkspaceInvite::class)->accept($invite, user());

        return redirect()->route('workspaces')->with('success', __('You joined the workspace successfully.'));
    }

    /**
     * Keeps the user's original letter case (CreateWorkspace::validate()
     * no longer forces lowercase) while stripping anything outside its
     * allowed character set — e.g. accents or punctuation in a real name —
     * so the suggestion is always a valid, single confirm-and-submit
     * default rather than one the user has to fix first.
     */
    private function suggestedWorkspaceName(string $userName): string
    {
        $name = Str::ascii($userName).' Workspace';
        $name = preg_replace('/[^A-Za-z0-9\- ]+/', '', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return $name !== '' ? $name : 'Workspace';
    }
}
