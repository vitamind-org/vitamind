<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Actions\Workspaces\GetWorkspaces;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceResource;
use App\Http\Resources\WorkspaceUserResource;
use App\Models\Workspace;
use App\Models\UserWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Patch;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Prefix('settings/workspaces')]
#[Middleware(['auth'])]
class WorkspaceController extends Controller
{
    #[Get('/', name: 'workspaces')]
    public function index(): Response
    {
        $this->authorize('viewAny', Workspace::class);

        return Inertia::render('workspaces/index', [
            'workspaces' => WorkspaceResource::collection(
                user()
                    ->allWorkspaces()
                    ->with(['users'])
                    ->simplePaginate(20)
            ),
            'invitations' => WorkspaceUserResource::collection(
                UserWorkspace::query()
                    ->where('email', user()->email)
                    ->whereNull('user_id')
                    ->simplePaginate(20)
            ),
        ]);
    }

    #[Get('/json', name: 'workspaces.json')]
    public function json(Request $request): ResourceCollection
    {
        $this->authorize('viewAny', Workspace::class);

        $workspaces = app(GetWorkspaces::class)->get(user(), $request->input(), 10);

        return WorkspaceResource::collection($workspaces);
    }

    #[Post('/', name: 'workspaces.store')]
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Workspace::class);

        app(CreateWorkspace::class)->create(user(), $request->input());

        return redirect()->route('workspaces')
            ->with('success', __('Workspace created successfully.'));
    }

    #[Patch('/{workspace}', name: 'workspaces.update')]
    public function update(Workspace $workspace, Request $request): RedirectResponse
    {
        $this->authorize('update', $workspace);

        app(UpdateWorkspace::class)->update($workspace, $request->input());

        return redirect()->route('workspaces')
            ->with('success', __('Workspace updated successfully.'));
    }

    #[Delete('/{workspace}', name: 'workspaces.destroy')]
    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);

        app(DeleteWorkspace::class)->delete(user(), $workspace, $request->input());

        return redirect()->route('workspaces')
            ->with('success', __('Workspace deleted successfully.'));
    }
}
